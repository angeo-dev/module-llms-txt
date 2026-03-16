<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Jsonl\Providers;

use Angeo\LlmsTxt\Api\Jsonl\DefaultProviderApi;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Store\Api\Data\StoreInterface;

class ProductProvider implements DefaultProviderApi
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly int $pageSize = 1000
    ) {}

    public function provide(StoreInterface $store): string
    {
        $storeId = $store->getId();
        $storeCode = $store->getCode();

        $page = 1;

        do {
            $collection = $this->collectionFactory->create();
            $collection->setStoreId($storeId);
            $collection->addStoreFilter($storeId);
            $collection->addAttributeToSelect(['sku','name','short_description','description','price']);
            $collection->addAttributeToFilter('status',1);
            $collection->addAttributeToFilter('visibility',['in'=>[2,3,4]]);
            $collection->setPageSize($this->pageSize);
            $collection->setCurPage($page);
            $collection->load();

            foreach ($collection as $product) {
                $short = strip_tags((string)$product->getShortDescription());
                $desc  = strip_tags((string)$product->getDescription());

                $data = [
                    "type" => "product",
                    "store" => $storeCode,
                    "id" => $product->getId(),
                    "sku" => $product->getSku(),
                    "title" => $product->getName(),
                    "price" => $product->getPrice(),
                    "currency" => $store->getCurrentCurrencyCode(),
                    "short_description" => substr($short,0,2000),
                    "description" => substr($desc,0,5000),
                    "url" => $product->getProductUrl(),
                    "embedding_text" => substr($product->getName() . ' ' . $short . ' ' . $desc,0,8000)
                ];
            }

            $page++;
            $collection->clear();

        } while ($page <= $collection->getLastPageNumber());

        return json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
}
