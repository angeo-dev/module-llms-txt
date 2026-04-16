<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Jsonl\Providers;

use Angeo\LlmsTxt\Api\ProviderInterface;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory;
use Magento\Store\Api\Data\StoreInterface;

class CmsPageProvider implements ProviderInterface
{

    public function __construct(
        private readonly CollectionFactory $collectionFactory,
        private readonly array $excludeIdentities = ['no-route', 'enable-cookies', 'privacy-policy-cookie-restriction-mode']
    ) {}

    public function provide(StoreInterface $store): string
    {
        $baseUrl = rtrim((string) $store->getBaseUrl(), '/');

        $pages = $this->collectionFactory->create();
        $pages->addStoreFilter((int) $store->getId());
        $pages->addFieldToFilter('is_active', 1);
        $pages->addFieldToFilter('identifier', ['nin' => $this->excludeIdentities]);
        $pages->addFieldToSelect(['title', 'identifier', 'content']);

        $lines = [];
        foreach ($pages as $page) {
            $content = mb_substr(
                preg_replace('/\s+/', ' ', strip_tags((string) $page->getContent())),
                0,
                8000
            );
            $content = trim((string) $content);

            $lines[] = json_encode([
                'type'           => 'cms_page',
                'store'          => $store->getName(),
                "id"             => $page->getId(),
                'title'          => $page->getTitle(),
                'identifier'     => $page->getIdentifier(),
                'url'            => "{$baseUrl}/{$page->getIdentifier()}",
                'content'        => $content,
                'embedding_text' => mb_substr(trim($page->getTitle() . ' ' . $content), 0, 8000),
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        return $lines ? implode("\n", $lines) . "\n" : '';
    }
}
