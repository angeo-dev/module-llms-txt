<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Generator;

use Angeo\LlmsTxt\Api\GenerationStatusRepositoryInterface;
use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\Output\AgentsMdBuilder;
use Angeo\LlmsTxt\Model\Output\FilePathResolver;
use Angeo\LlmsTxt\Model\Output\Signature;
use Angeo\LlmsTxt\Model\Output\SurfaceRegistry;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Generates agents.md — deliberately NOT part of the entity pipeline:
 * the file is store-level metadata, not catalog content, so it must
 * generate identically whether the store runs the legacy or single-pass
 * pipeline. GenerationService invokes it after either pipeline completes.
 *
 * Write strategy mirrors the pipelines: temp file + atomic rename, stale
 * file deleted when the format (or store) is disabled, success/failure
 * recorded in the same status repository the admin panel reads.
 *
 * @since 3.4.0
 */
class AgentsMdGenerator
{
    private const XML_PATH_STORE_NAME    = 'general/store_information/name';
    private const XML_PATH_SUPPORT_EMAIL = 'trans_email/ident_support/email';
    private const XML_PATH_META_DESCRIPTION = 'design/head/default_description';

    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly Filesystem $filesystem,
        private readonly FilePathResolver $pathResolver,
        private readonly AgentsMdBuilder $builder,
        private readonly SurfaceRegistry $surfaceRegistry,
        private readonly Signature $signature,
        private readonly GenerationStatusRepositoryInterface $statusRepository,
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly LoggerInterface $logger,
        private readonly ?\Angeo\LlmsTxt\Model\Output\SitemapUrlResolver $sitemapUrlResolver = null
    ) {
    }

    /**
     * Generate agents.md for one store or all stores.
     *
     * @return array<string, string> storeCode => outcome ('generated'|'skipped'|'failed')
     */
    public function generateAll(?string $storeCode = null): array
    {
        $outcomes = [];
        if (!$this->config->isEnabled()) {
            return $outcomes;
        }

        $stores = $storeCode !== null
            ? [$this->storeManager->getStore($storeCode)]
            : $this->storeManager->getStores();

        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $directory->create(FilePathResolver::SUB_DIR);

        foreach ($stores as $store) {
            $relativePath = $this->pathResolver->getRelativePath(
                OutputContextInterface::FORMAT_AGENTS_MD,
                (string) $store->getCode()
            );

            $isActive = !method_exists($store, 'isActive') || $store->isActive();
            if (!$isActive
                || $this->config->isStoreExcluded($store)
                || !$this->config->isAgentsMdEnabled($store)
            ) {
                $this->deleteIfExists($directory, $relativePath);
                $outcomes[(string) $store->getCode()] = 'skipped';
                continue;
            }

            $startedAt = microtime(true);
            try {
                $content = $this->builder->build($this->buildContext($store));
                if ($this->config->isSignatureEnabled($store)) {
                    $content .= $this->signature->forAgentsMd();
                }

                // Atomic publish: write sibling temp file, then rename.
                $tmpPath = $relativePath . '.tmp';
                $stream = $directory->openFile($tmpPath, 'w');
                try {
                    $bytes = $stream->write($content);
                } finally {
                    $stream->close();
                }
                $directory->renameFile($tmpPath, $relativePath);

                $this->statusRepository->recordSuccess(
                    (string) $store->getCode(),
                    OutputContextInterface::FORMAT_AGENTS_MD,
                    $bytes,
                    1,
                    microtime(true) - $startedAt
                );
                $outcomes[(string) $store->getCode()] = 'generated';
            } catch (\Throwable $e) {
                $this->logger->error(
                    '[Angeo LlmsTxt] agents.md generation failed for store '
                    . $store->getCode() . ': ' . $e->getMessage()
                );
                $this->statusRepository->recordFailure(
                    (string) $store->getCode(),
                    OutputContextInterface::FORMAT_AGENTS_MD,
                    $e->getMessage()
                );
                $outcomes[(string) $store->getCode()] = 'failed';
            }
        }

        return $outcomes;
    }

    private function buildContext(StoreInterface $store): array
    {
        $baseUrl = $store instanceof Store ? (string) $store->getBaseUrl() : '';
        $storeId = $store->getId();

        $summary = $this->config->getStoreSummary($store);
        if ($summary === '') {
            $summary = (string) $this->scopeConfig->getValue(
                self::XML_PATH_META_DESCRIPTION,
                ScopeInterface::SCOPE_STORE,
                $storeId
            );
        }

        return [
            'store_name' => (string) ($this->scopeConfig->getValue(
                self::XML_PATH_STORE_NAME,
                ScopeInterface::SCOPE_STORE,
                $storeId
            ) ?: $store->getName()),
            'base_url'   => $baseUrl,
            'summary'    => $summary,
            'locale'     => (string) $this->scopeConfig->getValue(
                'general/locale/code',
                ScopeInterface::SCOPE_STORE,
                $storeId
            ),
            'currency'   => $store instanceof Store ? (string) $store->getCurrentCurrencyCode() : '',
            'contact_email' => (string) $this->scopeConfig->getValue(
                self::XML_PATH_SUPPORT_EMAIL,
                ScopeInterface::SCOPE_STORE,
                $storeId
            ),
            'search_url_template' => rtrim($baseUrl, '/') . '/catalogsearch/result/?q={query}',
            'sitemap_url' => $this->sitemapUrlResolver !== null
                ? $this->sitemapUrlResolver->forStore($store, $baseUrl)
                : '',
            'agentic_sitemap_url' => $this->config->isAgenticSitemapEnabled($store)
                ? rtrim($baseUrl, '/') . '/sitemap_agentic_discovery.xml'
                : '',
            'pages'      => $this->resolvePages($store, $baseUrl),
            'surfaces'   => $this->surfaceRegistry->forStore($store),
        ];
    }

    /**
     * Policy / company links for agents.md. Config values are either absolute
     * URLs or paths relative to the store base URL (a CMS identifier such as
     * `shipping-policy` is exactly such a path), so merchants can point at a
     * CMS page without knowing its rewrite.
     *
     * @return array<string, string> label => absolute URL
     * @since 4.0.0
     */
    private function resolvePages(StoreInterface $store, string $baseUrl): array
    {
        $out = [];
        foreach ($this->config->getAgentsPageLinks($store) as $label => $value) {
            $out[$label] = preg_match('~^https?://~i', $value) === 1
                ? $value
                : rtrim($baseUrl, '/') . '/' . ltrim($value, '/');
        }

        return $out;
    }

    private function deleteIfExists(
        \Magento\Framework\Filesystem\Directory\WriteInterface $directory,
        string $relativePath
    ): void {
        try {
            if ($directory->isExist($relativePath)) {
                $directory->delete($relativePath);
            }
        } catch (\Throwable $e) {
            $this->logger->warning('[Angeo LlmsTxt] stale agents.md cleanup failed: ' . $e->getMessage());
        }
    }
}
