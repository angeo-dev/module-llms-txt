<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Llms\Providers;

use Angeo\LlmsTxt\Api\Llms\DefaultProviderApi;
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
        $output = "## PRODUCTS\n\n";

        $page = 1;

        do {

            $products = $this->collectionFactory->create();

            $products->setStoreId($store->getId());
            $products->addStoreFilter($store->getId());

            $products->addAttributeToSelect([
                'sku',
                'name',
                'price',
                'short_description',
                'description',
                'url_key'
            ]);

            $products->addAttributeToFilter('status', 1);
            $products->addAttributeToFilter('visibility', ['in' => [2,3,4]]);

            $products->setPageSize($this->pageSize);
            $products->setCurPage($page);

            $products->load();

            foreach ($products as $product) {

                $short = strip_tags((string)$product->getShortDescription());
                $desc = strip_tags((string)$product->getDescription());

                $output .= "TYPE: PRODUCT\n";
                $output .= "NAME: {$product->getName()}\n";
                $output .= "SKU: {$product->getSku()}\n";
                $output .= "URL: {$product->getProductUrl()}\n";
                $output .= "PRICE: {$product->getPrice()}\n";
                $output .= "SHORT_DESCRIPTION: " . substr($short, 0, 1000) . "\n";
                $output .= "DESCRIPTION: " . substr($desc, 0, 3000) . "\n";
                $output .= "\n";
            }

            $page++;
            $products->clear();

        } while ($page <= $products->getLastPageNumber());

        return $output;
    }
}
