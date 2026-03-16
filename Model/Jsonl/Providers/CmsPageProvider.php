<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Jsonl\Providers;

use Angeo\LlmsTxt\Api\Jsonl\DefaultProviderApi;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory;
use Magento\Store\Api\Data\StoreInterface;

class CmsPageProvider implements DefaultProviderApi
{
    public function __construct(
        private readonly CollectionFactory $collectionFactory
    ) {}

    public function provide(StoreInterface $store): string
    {
        $pages = $this->collectionFactory->create();
        $pages->addStoreFilter($store->getId());
        $pages->addFieldToFilter('is_active',1);

        foreach ($pages as $page) {
            $content = strip_tags((string)$page->getContent());
            $content = preg_replace('/\s+/', ' ', $content);

            $data = [
                "type" => "cms_page",
                "store" => $store->getCode(),
                "id" => $page->getId(),
                "title" => $page->getTitle(),
                "identifier" => $page->getIdentifier(),
                "url" => $store->getBaseUrl() . $page->getIdentifier(),
                "content" => substr($content,0,8000),
                "embedding_text" => substr($page->getTitle() . ' ' . $content,0,8000)
            ];

        }

        return json_encode($data, JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
}
