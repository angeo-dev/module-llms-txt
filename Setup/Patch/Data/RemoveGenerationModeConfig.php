<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Setup\Patch\Data;

use Magento\Framework\App\ResourceConnection;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * 4.0.0 removed the `generation_mode` switch: the single-pass pipeline is the
 * only pipeline. Saved values are now meaningless, and leaving them behind
 * shows up as orphan rows in `bin/magento config:show` and in config audits.
 *
 * Deleted across every scope in one statement — the field was global, but a
 * merchant could have written website/store rows through the CLI or a fixture.
 *
 * @since 4.0.0
 */
class RemoveGenerationModeConfig implements DataPatchInterface
{
    private const OBSOLETE_PATH = 'angeo_llms/performance/generation_mode';

    public function __construct(
        private readonly ResourceConnection $resourceConnection
    ) {
    }

    public function apply(): self
    {
        $connection = $this->resourceConnection->getConnection();
        $connection->delete(
            $this->resourceConnection->getTableName('core_config_data'),
            ['path = ?' => self::OBSOLETE_PATH]
        );

        return $this;
    }

    /**
     * @return string[]
     */
    public static function getDependencies(): array
    {
        return [];
    }

    /**
     * @return string[]
     */
    public function getAliases(): array
    {
        return [];
    }
}
