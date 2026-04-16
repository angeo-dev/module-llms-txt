<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Cron;

use Angeo\LlmsTxt\Model\JsonlGenerator;
use Angeo\LlmsTxt\Model\LlmsGenerator;
use Psr\Log\LoggerInterface;

/**
 * Daily scheduled generation of llms.txt and JSONL files.
 */
class LlmsGeneratorCron
{
    public function __construct(
        private readonly LlmsGenerator  $llmsGenerator,
        private readonly JsonlGenerator $jsonlGenerator,
        private readonly LoggerInterface $logger,
    ) {}

    public function execute(): void
    {
        try {
            $this->llmsGenerator->generate();
            $this->jsonlGenerator->generate();
        } catch (\Throwable $e) {
            $this->logger->error('[Angeo LlmsTxt] Cron generation failed: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }
}
