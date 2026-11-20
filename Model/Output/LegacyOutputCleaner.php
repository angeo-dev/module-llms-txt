<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Output;

use Magento\Framework\Filesystem;
use Psr\Log\LoggerInterface;

/**
 * Removes files that releases before 5.0.0 wrote to pub/media/angeo/llms/.
 *
 * Those files are served by the web server directly, so leaving them behind
 * after the upgrade would keep the catalog public at /media/angeo/llms/…
 * even when the module is disabled. Called from the upgrade data patch, from
 * every generation run and from `angeo:llms:clean`, so a missed patch or a
 * restored backup cannot bring them back for long.
 *
 * @since 5.0.0
 */
class LegacyOutputCleaner
{
    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * @return int Number of legacy files removed.
     */
    public function clean(): int
    {
        try {
            $media = $this->filesystem->getDirectoryWrite(FilePathResolver::LEGACY_DIRECTORY);
            if (!$media->isExist(FilePathResolver::SUB_DIR)) {
                return 0;
            }

            $count = 0;
            foreach ($media->read(FilePathResolver::SUB_DIR) as $path) {
                if ($media->isFile($path)) {
                    $count++;
                }
            }
            $media->delete(FilePathResolver::SUB_DIR);

            if ($count > 0) {
                $this->logger->info(sprintf(
                    '[Angeo LlmsTxt] Removed %d legacy file(s) from pub/media/%s.',
                    $count,
                    FilePathResolver::SUB_DIR
                ));
            }

            return $count;
        } catch (\Throwable $e) {
            $this->logger->warning(
                '[Angeo LlmsTxt] Could not remove legacy output from pub/media: ' . $e->getMessage()
            );

            return 0;
        }
    }
}
