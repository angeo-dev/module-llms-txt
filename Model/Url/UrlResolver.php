<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Url;

use Angeo\LlmsTxt\Api\UrlResolverInterface;
use Magento\Framework\App\ResourceConnection;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\Store;
use Magento\UrlRewrite\Model\UrlRewrite;

/**
 * Batch URL resolver — pulls every URL rewrite for a store in one query and
 * builds an in-memory map for O(1) lookups.
 *
 * Why: {@see \Magento\Catalog\Model\Product::getProductUrl()} triggers a per-product
 * url_rewrite query when the URL cache is cold; on a 5,000-SKU catalog generation,
 * that's 5,000 round-trips. With this resolver: 1 query, regardless of catalog size.
 *
 * @since 3.0.0
 */
class UrlResolver implements UrlResolverInterface
{
    private const ENTITY_TO_REWRITE = [
        UrlResolverInterface::ENTITY_PRODUCT  => 'product',
        UrlResolverInterface::ENTITY_CATEGORY => 'category',
        UrlResolverInterface::ENTITY_CMS_PAGE => 'cms-page',
    ];

    /** @var array<int, array<string, array<int, string>>>  storeId → entityType → entityId → request_path */
    private array $cache = [];

    /** @var array<int, string>  storeId → base URL (for absolute URL assembly) */
    private array $baseUrls = [];

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly StoreRepositoryInterface $storeRepository
    ) {
    }

    public function warmUp(int $storeId): void
    {
        if (isset($this->cache[$storeId])) {
            return;
        }

        $connection = $this->resourceConnection->getConnection();
        $tableName  = $this->resourceConnection->getTableName('url_rewrite');

        // One query, all entity types, redirect_type=0 (current URL only).
        // 5.0.0: metadata IS NULL keeps only the canonical rewrite. Without it
        // product rows also included every category-path variant
        // (women/tops/shirt.html) and, with no ORDER BY, which one "won"
        // changed from run to run — and the whole set was held in memory.
        $select = $connection->select()
            ->from(
                $tableName,
                ['entity_id', 'entity_type', 'request_path']
            )
            ->where('store_id = ?', $storeId)
            ->where('entity_type IN (?)', array_values(self::ENTITY_TO_REWRITE))
            ->where('redirect_type = ?', 0)
            ->where('metadata IS NULL')
            ->order('url_rewrite_id ASC');

        $bucket = [
            UrlResolverInterface::ENTITY_PRODUCT  => [],
            UrlResolverInterface::ENTITY_CATEGORY => [],
            UrlResolverInterface::ENTITY_CMS_PAGE => [],
        ];

        $reverse = array_flip(self::ENTITY_TO_REWRITE);
        // Stream rows instead of fetchAll(): avoids materializing a second full
        // copy of the result set, halving peak memory on very large rewrite tables.
        $statement = $connection->query($select);
        while (($row = $statement->fetch()) !== false) {
            $type = $reverse[$row['entity_type']] ?? null;
            if ($type === null) {
                continue;
            }
            $entityId = (int) $row['entity_id'];
            // Some setups produce duplicate rows when a rewrite has both a "main" and
            // an "alias" entry — the main one (no metadata) tends to sort first; we
            // keep the first one we see.
            if (!isset($bucket[$type][$entityId])) {
                $bucket[$type][$entityId] = (string) $row['request_path'];
            }
        }

        $this->cache[$storeId] = $bucket;
        $this->baseUrls[$storeId] = $this->resolveBaseUrl($storeId);
    }

    public function resolve(string $entityType, int $entityId, int $storeId): ?string
    {
        $this->warmUp($storeId);

        $path = $this->cache[$storeId][$entityType][$entityId] ?? null;
        if ($path === null) {
            return null;
        }

        return $this->baseUrls[$storeId] . '/' . ltrim($path, '/');
    }

    public function reset(): void
    {
        $this->cache = [];
        $this->baseUrls = [];
    }

    private function resolveBaseUrl(int $storeId): string
    {
        try {
            $store = $this->storeRepository->getById($storeId);
            return rtrim(\Angeo\LlmsTxt\Model\Output\OutputContext::storefrontBaseUrl($store), '/');
        } catch (\Throwable) {
            return '';
        }
    }
}
