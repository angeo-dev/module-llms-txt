<?php

declare(strict_types=1);

namespace Angeo\Model\Llms\Providers;

use Angeo\Api\Llms\DefaultProviderApi;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Store\Api\Data\StoreInterface;

class CategoryProvider implements DefaultProviderApi
{
    public function __construct(
        private readonly CollectionFactory $categoryCollectionFactory
    ) {}

    public function provide(StoreInterface $store): string
    {
        $output = "## CATEGORY DATA\n\n";

        $collection = $this->categoryCollectionFactory->create();

        $collection->setStoreId($store->getId());
        $collection->addAttributeToSelect([
            'name',
            'description',
            'url_key',
            'parent_id'
        ]);

        $collection->addAttributeToFilter('is_active', 1);

        $collection->addAttributeToFilter(
            'path',
            ['like' => '1/' . $store->getRootCategoryId() . '/%']
        );

        foreach ($collection as $category) {

            $description = strip_tags((string)$category->getDescription());
            $description = preg_replace('/\s+/', ' ', $description);

            $output .= "TYPE: CATEGORY\n";
            $output .= "NAME: {$category->getName()}\n";
            $output .= "URL: {$category->getUrl()}\n";
            $output .= "PARENT_ID: {$category->getParentId()}\n";
            $output .= "DESCRIPTION: " . substr($description, 0, 2000) . "\n";
            $output .= "\n";
        }

        return $output;
    }
}
