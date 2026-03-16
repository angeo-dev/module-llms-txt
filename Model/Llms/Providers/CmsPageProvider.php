<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Llms\Providers;

use Angeo\LlmsTxt\Api\Llms\DefaultProviderApi;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory;
use Magento\Store\Api\Data\StoreInterface;

class CmsPageProvider implements DefaultProviderApi
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {}

    public function provide(StoreInterface $store): string
    {
        $output = "## CMS PAGES\n\n";

        $pages = $this->collectionFactory->create();
        $pages->addStoreFilter($store->getId());
        $pages->addFieldToFilter('is_active', 1);

        foreach ($pages as $page) {

            $content = strip_tags((string)$page->getContent());
            $content = preg_replace('/\s+/', ' ', $content);

            $output .= "TYPE: PAGE\n";
            $output .= "TITLE: {$page->getTitle()}\n";
            $output .= "URL: {$store->getBaseUrl()}{$page->getIdentifier()}\n";
            $output .= "CONTENT: " . substr($content, 0, 4000) . "\n";
            $output .= "\n";
        }

        return $output;
    }
}
