<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Llms\Providers;

use Angeo\LlmsTxt\Api\ProviderInterface;
use Magento\Cms\Model\ResourceModel\Page\CollectionFactory;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Generates the ## Pages section in llms.txt format.
 *
 * Output per llmstxt.org spec:
 *   ## Pages
 *   - [Page Title](url): excerpt
 *
 * Excludes system pages (404, privacy, enable-cookies).
 */
class CmsPageProvider implements ProviderInterface
{
    private const CONTENT_MAX_LENGTH = 500;

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
            $title   = trim((string) $page->getTitle());
            $id      = $page->getIdentifier();
            $url     = "{$baseUrl}/{$id}";
            $excerpt = $this->extractExcerpt((string) $page->getContent(), self::CONTENT_MAX_LENGTH);

            $line    = "- [{$title}]({$url})";
            $line   .= $excerpt ? ": {$excerpt}" : '';
            $lines[] = $line;
        }

        if (empty($lines)) {
            return '';
        }

        return "## Pages\n\n" . implode("\n", $lines) . "\n\n";
    }

    private function extractExcerpt(string $content, int $maxLength): string
    {
        $clean = preg_replace('/\s+/', ' ', strip_tags($content));
        $clean = trim((string) $clean);
        if (!$clean) {
            return '';
        }
        $excerpt = mb_substr($clean, 0, $maxLength);
        return $excerpt . (mb_strlen($clean) > $maxLength ? '…' : '');
    }
}
