<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Pipeline;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\ResourceConnection;
use Psr\Log\LoggerInterface;

/**
 * Records "something was deleted" for {@see ChangeDetector}.
 *
 * ChangeDetector compares MAX(updated_at) timestamps. A deleted row takes its
 * timestamp with it, so a deleted product or page was invisible to it and
 * stayed in the files when "skip unchanged" was on. This writes the time of
 * the last delete into a row under angeo_llms/%, whose updated_at the
 * detector already reads.
 *
 * Written straight to core_config_data on purpose: nothing reads the value
 * through the config cache, so the config cache does not need cleaning.
 *
 * @since 5.0.0
 */
class ChangeMarker
{
    public const PATH = 'angeo_llms/state/last_delete';

    public function __construct(
        private readonly ResourceConnection $resourceConnection,
        private readonly LoggerInterface $logger
    ) {
    }

    public function markDeleted(): void
    {
        try {
            $connection = $this->resourceConnection->getConnection();
            $connection->insertOnDuplicate(
                $this->resourceConnection->getTableName('core_config_data'),
                [
                    'scope'    => ScopeConfigInterface::SCOPE_TYPE_DEFAULT,
                    'scope_id' => 0,
                    'path'     => self::PATH,
                    'value'    => (string) time() . '.' . substr(uniqid('', true), -6),
                ],
                ['value']
            );
        } catch (\Throwable $e) {
            $this->logger->warning('[Angeo LlmsTxt] Could not record delete marker: ' . $e->getMessage());
        }
    }
}
