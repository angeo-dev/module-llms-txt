<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Cron;

use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\JsonlGenerator;
use Angeo\LlmsTxt\Model\LlmsGenerator;
use Magento\Framework\App\Area;
use Magento\Framework\App\State as AppState;
use Psr\Log\LoggerInterface;

/**
 * Daily scheduled generation of llms.txt and JSONL files.
 *
 * Uses AppState::emulateAreaCode(AREA_FRONTEND) so that URL resolution,
 * locale, and design always return correct frontend values in cron context.
 */
class LlmsGeneratorCron
{
    public function __construct(
        private readonly LlmsGenerator  $llmsGenerator,
        private readonly JsonlGenerator $jsonlGenerator,
        private readonly AppState       $appState,
        private readonly LoggerInterface $logger,
        private readonly Config         $config,
    ) {}

    public function execute(): void
    {
        if (!$this->config->isEnabled()) {
            return;
        }

        try {
            $this->appState->emulateAreaCode(
                Area::AREA_FRONTEND,
                function () {
                    $this->llmsGenerator->generate();
                    $this->jsonlGenerator->generate();
                }
            );
        } catch (\Throwable $e) {
            $this->logger->error('[Angeo LlmsTxt] Cron generation failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }
}
