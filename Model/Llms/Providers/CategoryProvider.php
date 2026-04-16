<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Llms\Providers;

use Angeo\LlmsTxt\Api\ProviderInterface;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Store\Api\Data\StoreInterface;

/**
 * Generates the ## Categories section in llms.txt format.
 *
 * Output per llmstxt.org spec:
 *   ## Categories
 *   - [Category Name](https://store.com/cat): Short description
 */
class CategoryProvider implements ProviderInterface
{
    public function __construct(
        private readonly CollectionFactory $categoryCollectionFactory
    ) {}

    public function provide(StoreInterface $store): string
    {
        $collection = $this->categoryCollectionFactory->create();
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

            $url  = $category->getUrl();
            $desc = $this->cleanText((string) $category->getDescription(), 200);

            // Spec format: - [Name](url): description
            $line  = "- [{$name}]({$url})";
            $line .= $desc ? ": {$desc}" : '';
            $lines[] = $line;
        }

        if (empty($lines)) {
            return '';
        }

        return "## Categories\n\n" . implode("\n", $lines) . "\n\n";
    }

    private function cleanText(string $text, int $maxLength): string
    {
        $clean = preg_replace('/\s+/', ' ', strip_tags($text));
        $clean = trim((string) $clean);
        return $clean ? mb_substr($clean, 0, $maxLength) : '';
    }
}
