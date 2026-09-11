<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Output;

use Magento\Framework\App\ResourceConnection;
use Magento\Store\Api\Data\StoreInterface;
use Psr\Log\LoggerInterface;

/**
 * Absolute URL of the store's generated Magento sitemap.
 *
 * agents.md has carried a `Sitemap:` line since 3.4.0, but the value was
 * hardcoded empty with a "resolved in a follow-up" comment, so the line never
 * appeared. This finishes it.
 *
 * Reads the `sitemap` table directly rather than depending on
 * Magento_Sitemap's classes: the module is removable, and a missing sitemap is
 * a line we omit, not a reason to fail generation. If the merchant has more
 * than one sitemap for a store, the most recently generated one wins.
 *
 * @since 4.3.0
 */
class SitemapUrlResolver
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return string Absolute URL, or '' when the store has no sitemap.
     */
    public function forStore(StoreInterface $store, string $baseUrl): string
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $table = $this->resourceConnection->getTableName('sitemap');

            if (!$connection->isTableExists($table)) {
                return '';
            }

            $select = $connection->select()
                ->from($table, ['sitemap_path', 'sitemap_filename'])
                ->where('store_id = ?', (int) $store->getId())
                ->order('sitemap_time DESC')
                ->limit(1);

            $row = $connection->fetchRow($select);
            if (!is_array($row) || empty($row['sitemap_filename'])) {
                return '';
            }

            $path = trim((string) ($row['sitemap_path'] ?? ''), '/');
            $file = ltrim((string) $row['sitemap_filename'], '/');

            return rtrim($baseUrl, '/') . '/' . ($path !== '' ? $path . '/' : '') . $file;
        } catch (\Throwable $e) {
            $this->logger->warning(
                '[Angeo LlmsTxt] Could not resolve the sitemap URL: ' . $e->getMessage()
            );

            return '';
        }
    }
}
