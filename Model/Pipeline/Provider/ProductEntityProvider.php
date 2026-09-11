<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Pipeline\Provider;

use Angeo\LlmsTxt\Api\Data\EntityRecordInterface;
use Angeo\LlmsTxt\Api\EntityProviderInterface;
use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Api\SanitizerInterface;
use Angeo\LlmsTxt\Api\UrlResolverInterface;
use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\Data\EntityRecord;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Angeo\LlmsTxt\Model\Stock\SalableStatusResolver;
use Magento\CatalogInventory\Helper\Stock as StockHelper;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;

/**
 * THE single catalog pass: each product is loaded and sanitized exactly once;
 * the resulting record is rendered into every enabled format. Combined with
 * the 3.1.1 SQL-level stock filter and price-index pricing this is the core
 * win of the pipeline.
 *
 * 4.0.0 also exports the two facts an AI shopping agent needs beyond name and
 * price — salable status and the base image — plus any attribute codes the
 * merchant configures. All of it is loaded per collection page, never per
 * product.
 *
 * @since 3.2.0
 */
class ProductEntityProvider implements EntityProviderInterface
{
    /** Max of legacy short maxes: jsonl 2000, full 5000 → sanitize once at 5000. */
    public const SHORT_MAX = 5000;
    /** Max of legacy description maxes: jsonl 5000, full 5000. */
    public const DESC_MAX  = 5000;

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly SanitizerInterface $sanitizer,
        private readonly UrlResolverInterface $urlResolver,
        private readonly StockHelper $stockHelper,
        private readonly Config $config,
        private readonly ?SalableStatusResolver $salableResolver = null
    ) {
    }

    public function isApplicable(OutputContextInterface $context): bool
    {
        return $this->config->isProductsIncluded($context->getStore());
    }

    public function provide(OutputContextInterface $context): iterable
    {
        $context->setShared(OutputContextInterface::SHARED_ENTITY_TYPE, 'product');

        $store     = $context->getStore();
        $storeId   = (int) $store->getId();
        $websiteId = (int) $store->getWebsiteId();
        $pageSize  = $this->config->getCollectionPageSize($store);
        $limit     = $this->config->getProductLimit($store);
        $excludeOos = $this->config->isExcludeOutOfStock($store);
        $extraCodes  = $this->config->getExportAttributes($store);
        $wantStock   = $this->config->isAvailabilityIncluded($store);
        $useMsi      = $wantStock
            && $this->salableResolver !== null
            && $this->salableResolver->usesMsi($this->config->getStockSource($store));
        $wantImage   = $this->config->isImageIncluded($store);
        $brandCode   = $this->config->getBrandAttribute($store);
        $lastId    = 0;
        $emitted   = 0;

        while (true) {
            $collection = $this->collectionFactory->create();
            $collection->setStoreId($storeId);
            $collection->addStoreFilter($storeId);
            $select = ['sku', 'name', 'price', 'short_description', 'description', 'url_key'];
            if ($wantImage) {
                $select[] = 'image';
            }
            $collection->addAttributeToSelect(array_values(array_unique(array_merge($select, $extraCodes))));
            $collection->addAttributeToFilter('status', ['eq' => Status::STATUS_ENABLED]);
            $collection->addAttributeToFilter('visibility', [
                'in' => [
                    Visibility::VISIBILITY_IN_CATALOG,
                    Visibility::VISIBILITY_IN_SEARCH,
                    Visibility::VISIBILITY_BOTH,
                ],
            ]);
            $collection->addAttributeToFilter('entity_id', ['gt' => $lastId]);
            $collection->setOrder('entity_id', 'ASC');
            $collection->setPageSize($pageSize);
            $collection->setCurPage(1);
            $collection->addPriceData($context->getCustomerGroupId(), $websiteId);

            if ($excludeOos) {
                $this->stockHelper->addIsInStockFilterToCollection($collection);
            }

            $salableMap = [];
            if ($wantStock) {
                // Batch: one stock lookup per collection page, never one
                // round-trip per product (the 3.1.1 regression).
                if ($useMsi) {
                    $skus = [];
                    foreach ($collection as $product) {
                        $skus[] = (string) $product->getSku();
                    }
                    $salableMap = $this->salableResolver->forSkus($skus, $store);
                }
                if ($salableMap === []) {
                    // Legacy index — also the fallback when MSI could not
                    // answer. Better a known-source answer than a guess.
                    $this->stockHelper->addStockStatusToProducts($collection);
                }
            }

            $hasRows = false;
            foreach ($collection as $product) {
                $hasRows = true;
                $lastId  = (int) $product->getId();

                $url = $this->urlResolver->resolve(
                    UrlResolverInterface::ENTITY_PRODUCT,
                    (int) $product->getId(),
                    $storeId
                );
                if ($url === null) {
                    continue;
                }

                $rawShort = (string) $product->getShortDescription();
                $rawDesc  = (string) $product->getDescription();

                // Sanitize ONCE per field; dedupe identical raw inputs.
                $short = $this->sanitizer->sanitize($rawShort, $context, self::SHORT_MAX);
                $desc  = ($rawDesc === $rawShort)
                    ? $short
                    : $this->sanitizer->sanitize($rawDesc, $context, self::DESC_MAX);

                yield new EntityRecord(
                    type: EntityRecordInterface::TYPE_PRODUCT,
                    entityId: (int) $product->getId(),
                    name: trim((string) $product->getName()),
                    url: $url,
                    content: $desc,
                    shortContent: $short,
                    sku: (string) $product->getSku(),
                    price: $this->resolvePrice($product, $context),
                    inStock: $this->resolveInStock($product, $wantStock, $salableMap),
                    imageUrl: $wantImage ? $this->resolveImageUrl($product, $store) : null,
                    attributes: $this->resolveAttributes($product, $extraCodes, $brandCode)
                );

                $emitted++;
                if ($limit > 0 && $emitted >= $limit) {
                    $context->setShared('product_count', $emitted);
                    return;
                }
            }

            $collection->clear();

            if (!$hasRows) {
                break;
            }
        }

        $context->setShared('product_count', $emitted);
    }

    /**
     * Salable status for one product: the MSI answer when the batch lookup
     * produced one, the legacy index otherwise, null when availability export
     * is switched off.
     *
     * A SKU missing from a successful MSI batch means MSI has no salable
     * record for it, which is a definite "not salable" — not a reason to fall
     * back.
     *
     * @param array<string, bool> $salableMap
     * @since 4.2.0
     */
    private function resolveInStock(
        \Magento\Catalog\Model\Product $product,
        bool $wantStock,
        array $salableMap
    ): ?bool {
        if (!$wantStock) {
            return null;
        }

        if ($salableMap !== []) {
            return $salableMap[(string) $product->getSku()] ?? false;
        }

        return (bool) $product->getData('is_salable');
    }

    /**
     * Absolute URL of the product's base image (the original file, not a
     * resized cache variant — agents fetch it themselves and a cache path
     * would 404 until the frontend generated it).
     */
    private function resolveImageUrl(
        \Magento\Catalog\Model\Product $product,
        StoreInterface $store
    ): ?string {
        $image = trim((string) $product->getData('image'));
        if ($image === '' || $image === 'no_selection') {
            return null;
        }

        return rtrim($store->getBaseUrl(UrlInterface::URL_TYPE_MEDIA), '/')
            . '/catalog/product'
            . '/' . ltrim($image, '/');
    }

    /**
     * Exported attribute values, resolved to store-view labels where the
     * attribute has a source model. Empty values are dropped so the output
     * never carries keys with nothing behind them.
     *
     * @param string[] $codes
     * @return array<string, string>
     */
    private function resolveAttributes(
        \Magento\Catalog\Model\Product $product,
        array $codes,
        string $brandCode
    ): array {
        $out = [];

        foreach ($codes as $code) {
            $value = $this->attributeValue($product, $code);
            if ($value === '') {
                continue;
            }
            $key = ($brandCode !== '' && $code === $brandCode) ? 'brand' : $code;
            $out[$key] = $value;
        }

        return $out;
    }

    private function attributeValue(\Magento\Catalog\Model\Product $product, string $code): string
    {
        try {
            $text = $product->getAttributeText($code);
        } catch (\Throwable) {
            $text = null;
        }

        if (is_array($text)) {
            $text = implode(', ', array_map('strval', $text));
        }
        if (is_string($text) && trim($text) !== '') {
            return trim($text);
        }

        $raw = $product->getData($code);
        if (is_scalar($raw)) {
            return trim((string) $raw);
        }

        return '';
    }

    private function resolvePrice(
        \Magento\Catalog\Model\Product $product,
        OutputContextInterface $context
    ): float {
        $indexed = $product->getData('final_price');
        if ($indexed !== null) {
            return (float) $indexed;
        }
        $product->setCustomerGroupId($context->getCustomerGroupId());
        return (float) $product->getFinalPrice();
    }
}
