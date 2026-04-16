<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Llms\Providers;

use Angeo\LlmsTxt\Api\ProviderInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Generates the ## Products section in llms.txt format.
 *
 * Output per llmstxt.org spec:
 *   ## Products
 *   - [Product Name](url): short description — $price
 */
class ProductProvider implements ProviderInterface
{
    private const DESC_MAX_LENGTH = 500;

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly int $pageSize = 1000
    ) {}

    public function provide(StoreInterface $store): string
    {
        $lines    = [];
        $page     = 1;
        $storeId  = (int) $store->getId();

        do {
            $collection = $this->collectionFactory->create();
            $collection->setStoreId($storeId);
            $collection->addStoreFilter($storeId);
            $collection->addAttributeToSelect(['name', 'price', 'short_description', 'url_key']);
            $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
            $collection->addAttributeToFilter('visibility', [
                'in' => [Visibility::VISIBILITY_IN_CATALOG, Visibility::VISIBILITY_IN_SEARCH, Visibility::VISIBILITY_BOTH],
            ]);
            $collection->addUrlRewrite();
            $collection->setPageSize($this->pageSize);
            $collection->setCurPage($page);

            $lastPage = $collection->getLastPageNumber();

            foreach ($collection as $product) {
                $name  = trim((string) $product->getName());
                $url   = $product->getProductUrl();
                $price = $product->getPrice();
                $desc  = $this->cleanText((string) $product->getShortDescription(), self::DESC_MAX_LENGTH);

                $suffix = [];
                if ($desc) {
                    $suffix[] = $desc;
                }
                if ($price > 0) {
                    $suffix[] = number_format((float) $price, 2) . ' ' . $store->getCurrentCurrencyCode();
                }

                $line    = "- [{$name}]({$url})";
                $line   .= !empty($suffix) ? ': ' . implode(' — ', $suffix) : '';
                $lines[] = $line;
            }

            $collection->clear();
            $page++;

        } while ($page <= $lastPage);

        if (empty($lines)) {
            return '';
        }

        return "## Products\n\n" . implode("\n", $lines) . "\n\n";
    }

    private function cleanText(string $text, int $maxLength): string
    {
        $clean = preg_replace('/\s+/', ' ', strip_tags($text));
        return mb_substr(trim((string) $clean), 0, $maxLength);
    }
}
