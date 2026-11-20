<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Setup\Patch\Data;

use Angeo\LlmsTxt\Model\Output\LegacyOutputCleaner;
use Magento\Framework\Setup\Patch\DataPatchInterface;

/**
 * 5.0.0 moved generated files from pub/media to var. Delete the old, publicly
 * reachable copies on upgrade. The next generation run writes the new ones.
 *
 * @since 5.0.0
 */
class RemoveLegacyMediaOutput implements DataPatchInterface
{
    public function __construct(
        private readonly LegacyOutputCleaner $cleaner
    ) {
    }

    public function apply(): self
    {
        $this->cleaner->clean();

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
