<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Jsonl\Providers;

use Angeo\LlmsTxt\Api\ProviderInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Catalog\Model\Product\Visibility;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Fixed v1 bugs:
 * 1. json_encode was called AFTER the foreach — only the last product was encoded
 * 2. $collection->clear() before getLastPageNumber() — pagination never advanced beyond page 1
 */
class ProductProvider implements ProviderInterface
{
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
            $collection->addAttributeToSelect(['sku', 'name', 'price', 'short_description', 'description']);
            $collection->addAttributeToFilter('status', Status::STATUS_ENABLED);
            $collection->addAttributeToFilter('visibility', [
                'in' => [Visibility::VISIBILITY_IN_CATALOG, Visibility::VISIBILITY_IN_SEARCH, Visibility::VISIBILITY_BOTH],
            ]);
            $collection->addUrlRewrite();
            $collection->setPageSize($this->pageSize);
            $collection->setCurPage($page);

            $lastPage = $collection->getLastPageNumber();

            foreach ($collection as $product) {
                $short = mb_substr(preg_replace('/\s+/', ' ', strip_tags((string) $product->getShortDescription())), 0, 2000);
                $desc  = mb_substr(preg_replace('/\s+/', ' ', strip_tags((string) $product->getDescription())), 0, 5000);

                // FIX: json_encode INSIDE loop — v1 only encoded the last product
                $lines[] = json_encode([
                    'type'           => 'product',
                    'store'          => $store->getCode(),
                    'id'             => (int) $product->getId(),
                    'sku'            => $product->getSku(),
                    'title'          => $product->getName(),
                    'price'          => (float) $product->getPrice(),
                    'currency'       => $store->getCurrentCurrencyCode(),
                    'short_description' => trim($short),
                    'description'    => trim($desc),
                    'url'            => $product->getProductUrl(),
                    'embedding_text' => mb_substr(trim($product->getName() . ' ' . $short . ' ' . $desc), 0, 8000),
                ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            }

            $collection->clear();
            $page++;

        } while ($page <= $lastPage);

        return $lines ? implode("\n", $lines) . "\n" : '';
    }
}
