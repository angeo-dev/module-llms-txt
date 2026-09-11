<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Controller\Index;

use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Api\SanitizerInterface;
use Angeo\LlmsTxt\Controller\Router;
use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\Output\OutputContextFactory;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Model\Product\Attribute\Source\Status as ProductStatus;
use Magento\Catalog\Model\Product\Visibility as ProductVisibility;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use Psr\Log\LoggerInterface;

/**
 * Serves the per-entity Markdown mirror requested by `/{url_key}.md`.
 *
 * For every product/category/CMS page rewrite in the store, the corresponding
 * `.md` URL serves a clean markdown rendering of the entity (no theme, no
 * navigation, no checkout chrome) suitable for LLM ingestion.
 *
 * Spec reference: llmstxt.org recommends pages with useful LLM content expose
 * a markdown version at the same URL with `.md` appended.
 *
 * Security / availability hardening (3.1.0):
 *  - Disabled / non-visible products, inactive categories, and inactive CMS
 *    pages return 404 even when a stale url_rewrite row still exists
 *    (information-disclosure fix).
 *  - Rendered markdown is cached in the Magento cache (TTL = HTTP cache TTL)
 *    so repeated crawls do not re-trigger entity loads, CMS directive
 *    resolution, and DOM-based sanitization on every request (DoS fix).
 *  - Misses (unknown paths) are negative-cached for a short window to blunt
 *    enumeration sweeps.
 *  - `X-Content-Type-Options: nosniff` and `Content-Length` are always sent.
 *
 * @since 3.0.0
 */
class MdMirror implements ActionInterface, HttpGetActionInterface
{
    /** Cache tag for all rendered mirrors — flushed via standard cache:clean. */
    public const CACHE_TAG = 'ANGEO_LLMS_MD';

    /** Negative-cache lifetime for unknown paths (seconds). */
    private const NOT_FOUND_TTL = 300;

    /** Sentinel stored in cache for known-missing paths. */
    private const NOT_FOUND_SENTINEL = "\0ANGEO_LLMS_404\0";

    /** Upper bound for a cacheable request path; longer paths are rejected outright. */
    private const MAX_PATH_LENGTH = 1024;

    public function __construct(
        private readonly HttpResponse $response,
        private readonly RequestInterface $request,
        private readonly StoreManagerInterface $storeManager,
        private readonly RawFactory $resultRawFactory,
        private readonly Config $config,
        private readonly UrlFinderInterface $urlFinder,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly CategoryRepositoryInterface $categoryRepository,
        private readonly PageRepositoryInterface $pageRepository,
        private readonly SanitizerInterface $sanitizer,
        private readonly OutputContextFactory $contextFactory,
        private readonly CacheInterface $cache,
        private readonly LoggerInterface $logger,
        ?\Angeo\LlmsTxt\Model\Output\Signature $signature = null
    ) {
        $this->signature = $signature ?? new \Angeo\LlmsTxt\Model\Output\Signature();
    }

    /** @since 3.3.0 */
    private readonly \Angeo\LlmsTxt\Model\Output\Signature $signature;

    public function execute()
    {
        try {
            $store = $this->storeManager->getStore();

            if (
                !$this->config->isEnabled($store)
                || $this->config->isStoreExcluded($store)
                || !$this->config->isMdMirrorEnabled($store)
            ) {
                return $this->notFound();
            }

            $path = (string) $this->request->getParam(Router::PARAM_MD_PATH);
            if (
                $path === ''
                || strlen($path) > self::MAX_PATH_LENGTH
                || !str_ends_with($path, '.md')
            ) {
                return $this->notFound();
            }

            // Strip .md suffix and look up the rewrite.
            $requestPath = substr($path, 0, -3);
            $requestPath = ltrim($requestPath, '/');

            // ── Cache lookup (positive + negative) ──────────────────────────
            $cacheKey = $this->buildCacheKey((int) $store->getId(), $requestPath);
            $cached   = $this->cache->load($cacheKey);
            if ($cached !== false && $cached !== '') {
                if ($cached === self::NOT_FOUND_SENTINEL) {
                    return $this->notFound();
                }
                return $this->buildResult($cached);
            }

            $rewrite = $this->urlFinder->findOneByData([
                UrlRewrite::REQUEST_PATH => $requestPath,
                UrlRewrite::STORE_ID     => (int) $store->getId(),
            ]);
            if ($rewrite === null) {
                // Fall back to the ".html"-suffixed rewrite; many catalogs index
                // product/category rewrites with a configured ".html" URL suffix.
                $rewrite = $this->urlFinder->findOneByData([
                    UrlRewrite::REQUEST_PATH => $requestPath . '.html',
                    UrlRewrite::STORE_ID     => (int) $store->getId(),
                ]);
            }
            if ($rewrite === null || $rewrite->getRedirectType() !== 0) {
                $this->rememberNotFound($cacheKey);
                return $this->notFound();
            }

            $context = $this->contextFactory->create(
                $store,
                OutputContextInterface::FORMAT_LLMS_FULL_TXT,
                OutputContextInterface::VERBOSITY_FULL
            );

            $markdown = $this->renderEntity($rewrite, $context);
            if ($markdown === null) {
                // Entity is disabled / hidden / gone — treat exactly like an
                // unknown path so the response does not confirm its existence.
                $this->rememberNotFound($cacheKey);
                return $this->notFound();
            }

            // Attribution signature — appended BEFORE caching so the cached
            // copy and a fresh render are byte-identical (Content-Length is
            // computed from the final string in buildResult()).
            if ($this->config->isSignatureEnabled($store)) {
                $markdown .= $this->signature->forMdMirror();
            }

            $this->cache->save(
                $markdown,
                $cacheKey,
                [self::CACHE_TAG],
                $this->config->getHttpCacheTtl()
            );

            return $this->buildResult($markdown);
        } catch (\Throwable $e) {
            $this->logger->error('[Angeo LlmsTxt] MdMirror error: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            return $this->notFound();
        }
    }

    private function buildResult(string $markdown): Raw
    {
        $result = $this->resultRawFactory->create();
        $result->setHeader('Content-Type', 'text/markdown; charset=utf-8', true);
        $result->setHeader('Content-Length', (string) strlen($markdown), true);
        $result->setHeader('Cache-Control', sprintf(
            'public, max-age=%d',
            $this->config->getHttpCacheTtl()
        ), true);
        $result->setHeader('X-Content-Type-Options', 'nosniff', true);
        $result->setHeader('X-Robots-Tag', 'noindex, follow', true);
        $result->setContents($markdown);
        return $result;
    }

    private function buildCacheKey(int $storeId, string $requestPath): string
    {
        return 'angeo_llms_md_' . $storeId . '_' . hash('sha256', $requestPath);
    }

    private function rememberNotFound(string $cacheKey): void
    {
        $this->cache->save(
            self::NOT_FOUND_SENTINEL,
            $cacheKey,
            [self::CACHE_TAG],
            self::NOT_FOUND_TTL
        );
    }

    private function renderEntity(UrlRewrite $rewrite, OutputContextInterface $context): ?string
    {
        $entityType = $rewrite->getEntityType();
        $entityId   = (int) $rewrite->getEntityId();
        $storeId    = (int) $context->getStore()->getId();

        return match ($entityType) {
            'product'  => $this->renderProduct($entityId, $storeId, $context),
            'category' => $this->renderCategory($entityId, $storeId, $context),
            'cms-page' => $this->renderCmsPage($entityId, $context),
            default    => null,
        };
    }

    private function renderProduct(int $id, int $storeId, OutputContextInterface $context): ?string
    {
        try {
            $product = $this->productRepository->getById($id, false, $storeId);
        } catch (\Throwable) {
            return null;
        }

        // SECURITY: a url_rewrite row can outlive the product's saleable state.
        // Never serve disabled or catalog-invisible products — they may be
        // embargoed, recalled, or intentionally pulled from the storefront.
        if ((int) $product->getStatus() !== ProductStatus::STATUS_ENABLED) {
            return null;
        }
        if ((int) $product->getVisibility() === ProductVisibility::VISIBILITY_NOT_VISIBLE) {
            return null;
        }
        // Respect the store/website assignment of the loaded scope.
        $websiteIds = array_map('intval', (array) $product->getWebsiteIds());
        $websiteId  = (int) $context->getStore()->getWebsiteId();
        if ($websiteIds !== [] && !in_array($websiteId, $websiteIds, true)) {
            return null;
        }

        $context->setShared(OutputContextInterface::SHARED_ENTITY_TYPE, 'product');

        $title = (string) $product->getName();
        $short = $this->sanitizer->sanitize((string) $product->getShortDescription(), $context);
        $desc  = $this->sanitizer->sanitize((string) $product->getDescription(), $context);
        $product->setCustomerGroupId($context->getCustomerGroupId());
        $price = (float) $product->getFinalPrice();

        $out = "# {$title}\n\n";
        $out .= sprintf(
            "> SKU: %s · Price: %s %s\n\n",
            $product->getSku(),
            number_format($price, 2, '.', ''),
            $context->getCurrencyCode()
        );
        if ($short !== '') {
            $out .= $short . "\n\n";
        }
        if ($desc !== '' && $desc !== $short) {
            $out .= $desc . "\n";
        }
        return $out;
    }

    private function renderCategory(int $id, int $storeId, OutputContextInterface $context): ?string
    {
        try {
            $category = $this->categoryRepository->get($id, $storeId);
        } catch (\Throwable) {
            return null;
        }

        // SECURITY: do not serve disabled categories via stale rewrites.
        if (!(bool) $category->getIsActive()) {
            return null;
        }

        $context->setShared(OutputContextInterface::SHARED_ENTITY_TYPE, 'category');

        $title = (string) $category->getName();
        $desc  = $this->sanitizer->sanitize((string) $category->getDescription(), $context);

        $out = "# {$title}\n\n";
        if ($desc !== '') {
            $out .= $desc . "\n";
        }
        return $out;
    }

    private function renderCmsPage(int $id, OutputContextInterface $context): ?string
    {
        try {
            $page = $this->pageRepository->getById($id);
        } catch (\Throwable) {
            return null;
        }

        // SECURITY: disabled CMS pages must not leak via the mirror.
        if (!(bool) $page->isActive()) {
            return null;
        }

        $context->setShared(OutputContextInterface::SHARED_ENTITY_TYPE, 'cms-page');

        $title   = (string) $page->getTitle();
        $content = $this->sanitizer->sanitize((string) $page->getContent(), $context);

        $out = "# {$title}\n\n";
        if ($content !== '') {
            $out .= $content . "\n";
        }
        return $out;
    }

    private function notFound(): HttpResponse
    {
        $this->response->setHttpResponseCode(404);
        $this->response->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
        $this->response->setHeader('X-Content-Type-Options', 'nosniff', true);
        $this->response->setBody("Not found.\n");
        return $this->response;
    }
}
