<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Jsonl\Providers;

use Angeo\LlmsTxt\Api\Jsonl\DefaultProviderApi;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Store\Api\Data\StoreInterface;

class CategoryProvider implements DefaultProviderApi
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {}

    public function provide(StoreInterface $store): string
    {
        $collection = $this->collectionFactory->create();
        $collection->setStoreId($store->getId());
        $collection->addAttributeToSelect(['name','description','url_key','parent_id']);
        $collection->addAttributeToFilter('is_active',1);

        foreach ($collection as $category) {
            $desc = strip_tags((string)$category->getDescription());
            $desc = preg_replace('/\s+/', ' ', $desc);

            $data = [
                "type" => "category",
                "store" => $store->getCode(),
                "id" => $category->getId(),
                "name" => $category->getName(),
                "parent_id" => $category->getParentId(),
                "url" => $category->getUrl(),
                "description" => substr($desc,0,4000),
                "embedding_text" => substr($category->getName() . ' ' . $desc,0,8000)
            ];
        }

        return json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
}
