<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Pipeline\Provider;

use Angeo\LlmsTxt\Api\Data\EntityRecordInterface;
use Angeo\LlmsTxt\Api\EntityProviderInterface;
use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Api\SanitizerInterface;
use Angeo\LlmsTxt\Api\UrlResolverInterface;
use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\Data\EntityRecord;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;

/**
 * Streams category records. Description is sanitized ONCE at the maximum
 * length any format needs (4000); renderers truncate down (compact: 200).
 *
 * @since 3.2.0
 */
class CategoryEntityProvider implements EntityProviderInterface
{
    /** Max of legacy DESC_MAX (jsonl, 4000) and DESC_MAX_FULL (full txt, 4000). */
    public const CONTENT_MAX = 4000;

    public function __construct(
        private readonly CollectionFactory $categoryCollectionFactory,
        private readonly SanitizerInterface $sanitizer,
        private readonly UrlResolverInterface $urlResolver,
        private readonly Config $config
    ) {
    }

    public function isApplicable(OutputContextInterface $context): bool
    {
        return $this->config->isCategoriesIncluded($context->getStore());
    }

    public function provide(OutputContextInterface $context): iterable
    {
        $context->setShared(OutputContextInterface::SHARED_ENTITY_TYPE, 'category');

        $store = $context->getStore();
        $storeId = (int) $store->getId();
        $rootCategoryId = (int) $store->getRootCategoryId();

        $collection = $this->categoryCollectionFactory->create();
        $collection->setStoreId($storeId);
        $collection->addAttributeToSelect(['name', 'description', 'url_key']);
        $collection->addAttributeToFilter('is_active', ['eq' => 1]);
        $collection->addAttributeToFilter('path', ['like' => '1/' . $rootCategoryId . '/%']);
        $collection->setOrder('position', 'ASC');

        $count = 0;
        foreach ($collection as $category) {
            $name = trim((string) $category->getName());
            if ($name === '') {
                continue;
            }

            $url = $this->urlResolver->resolve(
                UrlResolverInterface::ENTITY_CATEGORY,
                (int) $category->getId(),
                $storeId
            );
            if ($url === null) {
                continue;
            }

            // Sanitize once — renderers only truncate.
            $description = $this->sanitizer->sanitize(
                (string) $category->getDescription(),
                $context,
                self::CONTENT_MAX
            );

            yield new EntityRecord(
                type: EntityRecordInterface::TYPE_CATEGORY,
                entityId: (int) $category->getId(),
                name: $name,
                url: $url,
                content: $description
            );
            $count++;
        }

        $context->setShared('category_count', $count);
    }
}
