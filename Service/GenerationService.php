<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Service;

use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\Generator\AbstractGenerator;
use Angeo\LlmsTxt\Model\Generator\GenerationSummary;
use Angeo\LlmsTxt\Model\Generator\JsonlGenerator;
use Angeo\LlmsTxt\Model\Generator\LlmsFullTxtGenerator;
use Angeo\LlmsTxt\Model\Generator\LlmsTxtGenerator;
use Angeo\LlmsTxt\Model\Pipeline\SinglePassGenerator;
use Magento\Framework\App\CacheInterface;

/**
 * Single entry point for "run every enabled generator".
 *
 * Used by the CLI command, cron, admin "Generate Now" action, and the async
 * consumer. Routes to the active pipeline (see
 * `angeo_llms/performance/generation_mode`):
 *
 *  - legacy (default): three per-format generators, pre-3.2 behavior;
 *  - single_pass:      one catalog pass per store renders all formats.
 *
 * The returned structure (summaries keyed by format) is identical in both
 * modes, so callers never need to care which pipeline ran.
 *
 * @since 3.0.0
 */
class GenerationService
{
    /**
     * Bundled legacy provider classes — anything ELSE registered on the
     * deprecated generators is a third-party extension and is executed by the
     * single-pass pipeline through the legacy compatibility pass.
     */
    private const BUNDLED_LEGACY_PROVIDERS = [
        \Angeo\LlmsTxt\Model\Provider\Llms\StoreProvider::class,
        \Angeo\LlmsTxt\Model\Provider\Llms\CategoryProvider::class,
        \Angeo\LlmsTxt\Model\Provider\Llms\CmsPageProvider::class,
        \Angeo\LlmsTxt\Model\Provider\Llms\ProductProvider::class,
        \Angeo\LlmsTxt\Model\Provider\Jsonl\StoreProvider::class,
        \Angeo\LlmsTxt\Model\Provider\Jsonl\CategoryProvider::class,
        \Angeo\LlmsTxt\Model\Provider\Jsonl\CmsPageProvider::class,
        \Angeo\LlmsTxt\Model\Provider\Jsonl\ProductProvider::class,
    ];

    public function __construct(
        private readonly LlmsTxtGenerator $llmsTxtGenerator,
        private readonly LlmsFullTxtGenerator $llmsFullTxtGenerator,
        private readonly JsonlGenerator $jsonlGenerator,
        private readonly CacheInterface $cache,
        private readonly Config $config,
        private readonly SinglePassGenerator $singlePassGenerator,
        ?\Angeo\LlmsTxt\Model\Generator\AgentsMdGenerator $agentsMdGenerator = null
    ) {
        $this->agentsMdGenerator = $agentsMdGenerator;
    }

    /** @since 3.4.0 — optional for BC with 3.2/3.3 constructor signatures. */
    private readonly ?\Angeo\LlmsTxt\Model\Generator\AgentsMdGenerator $agentsMdGenerator;

    /**
     * Run all generators.
     *
     * @param string|null $storeCode  Restrict to one store, or null for all.
     * @param array<string, bool> $skip  ['llms_txt' => true, 'llms_full_txt' => false, 'jsonl' => false]
     * @return array<string, GenerationSummary>  keyed by format
     */
    public function generateAll(?string $storeCode = null, array $skip = []): array
    {
        $summaries = $this->config->isSinglePassEnabled()
            ? $this->singlePassGenerator->generateAll(
                $storeCode,
                $skip,
                $this->collectLegacyExtraProviders()
            )
            : $this->runLegacy($storeCode, $skip);

        // agents.md is store-level metadata, not catalog content — generated
        // by its own generator so it works identically under both pipelines.
        $this->agentsMdGenerator?->generateAll($storeCode);

        // Invalidate cached /{url}.md mirrors: content was just regenerated, so
        // mirrors must not keep serving the previous catalog state for a full TTL.
        $this->cache->clean([\Angeo\LlmsTxt\Controller\Index\MdMirror::CACHE_TAG]);

        return $summaries;
    }

    /**
     * Pre-3.2 per-format pipeline.
     *
     * @param array<string, bool> $skip
     * @return array<string, GenerationSummary>
     * @deprecated 3.2.0 The legacy pipeline will be removed in 4.0.0; switch
     *             `angeo_llms/performance/generation_mode` to single_pass.
     */
    private function runLegacy(?string $storeCode, array $skip): array
    {
        $summaries = [];

        if (empty($skip['llms_txt'])) {
            $summaries['llms_txt'] = $this->llmsTxtGenerator->generate($storeCode);
        }
        if (empty($skip['llms_full_txt'])) {
            $summaries['llms_full_txt'] = $this->llmsFullTxtGenerator->generate($storeCode);
        }
        if (empty($skip['jsonl'])) {
            $summaries['jsonl'] = $this->jsonlGenerator->generate($storeCode);
        }

        return $summaries;
    }

    /**
     * Third-party legacy ProviderInterface implementations registered on the
     * deprecated generators, keyed by format — fed into the single-pass
     * pipeline's compatibility pass so existing extensions keep contributing
     * output without code changes.
     *
     * @return array<string, \Angeo\LlmsTxt\Api\ProviderInterface[]>
     */
    private function collectLegacyExtraProviders(): array
    {
        $map = [
            OutputContextInterface::FORMAT_LLMS_TXT      => $this->llmsTxtGenerator,
            OutputContextInterface::FORMAT_LLMS_FULL_TXT => $this->llmsFullTxtGenerator,
            OutputContextInterface::FORMAT_JSONL         => $this->jsonlGenerator,
        ];

        $extras = [];
        foreach ($map as $format => $generator) {
            $extras[$format] = [];
            if (!$generator instanceof AbstractGenerator) {
                continue;
            }
            foreach ($generator->getProviders() as $provider) {
                if (!in_array($provider::class, self::BUNDLED_LEGACY_PROVIDERS, true)) {
                    $extras[$format][] = $provider;
                }
            }
        }

        return $extras;
    }
}
