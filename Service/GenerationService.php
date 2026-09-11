<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Service;

use Angeo\LlmsTxt\Model\Generator\AgentsMdGenerator;
use Angeo\LlmsTxt\Model\Generator\GenerationSummary;
use Angeo\LlmsTxt\Model\Pipeline\SinglePassGenerator;
use Magento\Framework\App\CacheInterface;

/**
 * Single entry point for generation — used by CLI, cron, and the admin
 * "Generate Now" consumer.
 *
 * 4.0.0: the pre-3.2 per-format pipeline and the `generation_mode` switch are
 * gone. Every run is one catalog pass per store
 * ({@see SinglePassGenerator}); agents.md is produced by its own generator
 * because it is store-level metadata rather than catalog content.
 *
 * @since 3.0.0
 */
class GenerationService
{
    public function __construct(
        private readonly SinglePassGenerator $singlePassGenerator,
        private readonly AgentsMdGenerator $agentsMdGenerator,
        private readonly CacheInterface $cache
    ) {
    }

    /**
     * Run generation for one store or all eligible stores.
     *
     * @param string|null $storeCode Restrict to one store, or null for all.
     * @param array<string, bool> $skip ['llms_txt' => true, 'llms_full_txt' => false, 'jsonl' => false]
     * @param bool $force Rebuild even when nothing changed since the last run.
     * @return array<string, GenerationSummary> keyed by format
     */
    public function generateAll(?string $storeCode = null, array $skip = [], bool $force = false): array
    {
        $summaries = $this->singlePassGenerator->generateAll($storeCode, $skip, $force);

        // agents.md is store-level metadata, not catalog content — its own
        // generator writes it after the catalog pass completes.
        $this->agentsMdGenerator->generateAll($storeCode);

        // Invalidate cached /{url}.md mirrors: content was just regenerated, so
        // mirrors must not keep serving the previous catalog state for a full TTL.
        $this->cache->clean([\Angeo\LlmsTxt\Controller\Index\MdMirror::CACHE_TAG]);

        return $summaries;
    }
}
