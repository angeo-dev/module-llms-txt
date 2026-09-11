<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Pipeline;

use Magento\Framework\App\ResourceConnection;
use Magento\Store\Api\Data\StoreInterface;
use Psr\Log\LoggerInterface;

/**
 * Answers one question: has anything that ends up in the generated files
 * changed since a given moment?
 *
 * Used to skip a full catalog pass on stores that did not change overnight.
 * Deliberately cheap — four MAX() reads on indexed timestamp columns, no
 * catalog iteration.
 *
 * IMPORTANT — what this cannot see. It reads entity timestamps, so it detects
 * anything that writes `updated_at`: product and category edits, CMS page
 * edits, and changes to this module's own configuration. It does NOT detect:
 *
 *   - stock movements (`cataloginventory_stock_item` has no timestamp column),
 *     so a product going out of stock does not by itself trigger a rebuild;
 *   - prices that change without touching the product row — catalog price
 *     rules, scheduled updates applied by an indexer, tier prices imported
 *     straight into their own tables.
 *
 * That is why the feature is off by default. On a store with moving stock or
 * catalog rules, leave it off and pay for the nightly pass.
 *
 * @since 4.1.0
 */
class ChangeDetector
{
    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Unix timestamp of the most recent relevant change, or null when it
     * cannot be determined — in which case callers MUST regenerate.
     */
    public function lastChangeAt(StoreInterface $store): ?int
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $timestamps = [];

            foreach (
                [
                    'catalog_product_entity'  => 'updated_at',
                    'catalog_category_entity' => 'updated_at',
                    'cms_page'                => 'update_time',
                ] as $table => $column
            ) {
                $tableName = $this->resourceConnection->getTableName($table);
                if (!$connection->isTableExists($tableName)) {
                    continue;
                }
                $select = $connection->select()->from($tableName, ['max' => 'MAX(' . $column . ')']);
                $timestamps[] = $connection->fetchOne($select);
            }

            // A config change must force a rebuild even when the catalog is
            // untouched — switching a format on has to take effect tonight.
            $configTable = $this->resourceConnection->getTableName('core_config_data');
            $select = $connection->select()
                ->from($configTable, ['max' => 'MAX(updated_at)'])
                ->where('path LIKE ?', 'angeo_llms/%');
            $timestamps[] = $connection->fetchOne($select);

            $latest = null;
            foreach ($timestamps as $value) {
                $unix = $this->toUnixTime($value);
                if ($unix !== null && ($latest === null || $unix > $latest)) {
                    $latest = $unix;
                }
            }

            return $latest;
        } catch (\Throwable $e) {
            // Never let a detection failure silently skip generation.
            $this->logger->warning(sprintf(
                '[Angeo LlmsTxt] Change detection failed for store %s: %s — regenerating.',
                $store->getCode(),
                $e->getMessage()
            ));

            return null;
        }
    }

    /**
     * Magento stores these columns in UTC.
     */
    private function toUnixTime(mixed $value): ?int
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return (new \DateTimeImmutable($value, new \DateTimeZone('UTC')))->getTimestamp();
        } catch (\Throwable) {
            return null;
        }
    }
}
