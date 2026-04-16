<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Jsonl\Providers;

use Angeo\LlmsTxt\Api\ProviderInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Store\Api\Data\StoreInterface;

class CategoryProvider implements ProviderInterface
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {}

    public function provide(StoreInterface $store): string
    {
        $collection = $this->collectionFactory->create();
        $collection->setStoreId($store->getId());
        $collection->addAttributeToSelect(['name', 'description', 'url_key']);
        $collection->addAttributeToFilter('is_active', 1);
        $collection->addAttributeToFilter(
            'path',
            ['like' => '1/' . $store->getRootCategoryId() . '/%']
        );

        $lines = [];
        foreach ($collection as $category) {
            $name = trim((string) $category->getName());
            if (!$name) {
                continue;
            }

            $desc = preg_replace('/\s+/', ' ', strip_tags((string) $category->getDescription()));
            $desc = mb_substr(trim((string) $desc), 0, 4000);

            $lines[] = json_encode([
                'type'           => 'category',
                'store'          => $store->getName(),
                "id"             => $category->getId(),
                'name'           => $name,
                'url'            => $category->getUrl(),
                'description'    => $desc,
                'embedding_text' => mb_substr($name . ' ' . $desc, 0, 8000),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $lines ? implode("\n", $lines) . "\n" : '';
    }
}
