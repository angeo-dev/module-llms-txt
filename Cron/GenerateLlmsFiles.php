<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Cron;

use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Service\GenerationService;
use Psr\Log\LoggerInterface;

/**
 * Scheduled generation entry point.
 *
 * Schedule comes from the admin config (`angeo_llms/cron/schedule`); default is
 * daily at 02:00 server time.
 *
 * Frontend emulation, locking and atomic writes are handled by the pipeline;
 * this class is just the cron shim.
 *
 * @since 3.0.0
 */
class GenerateLlmsFiles
{
    public function __construct(
        private readonly GenerationService $generationService,
        private readonly LoggerInterface $logger,
        private readonly Config $config
    ) {
    }

    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        try {
            $this->generationService->generateAll();
        } catch (\Throwable $e) {
            $this->logger->error(
                '[Angeo LlmsTxt] Cron generation failed: ' . $e->getMessage(),
                ['exception' => $e]
            );
        }
    }
}
