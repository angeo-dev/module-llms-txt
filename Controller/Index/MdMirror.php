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
use Angeo\LlmsTxt\Model\Access\RestrictedCatalogGuard;
use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\Output\OutputContextFactory;
use Angeo\LlmsTxt\Model\Text\MarkdownText;
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
 *  - 5.0.0: misses are NOT cached any more. Negative caching let a sweep of
 *    random paths fill the cache backend with one entry per request.
 *  - 5.0.0: the path is read from the request URL, never from a parameter,
 *    so the standard-router route (/llms/index/mdmirror?md_path=…) serves
 *    nothing.
 *  - 5.0.0: prices are labelled with the base currency they are stored in
 *    (was: the visitor's display currency, cached for everyone).
 *  - 5.0.0: catalog text is markdown-escaped ({@see MarkdownText}) and the
 *    response carries X-Magento-Tags so Varnish purges it on entity save.
 *  - `X-Content-Type-Options: nosniff` and `Content-Length` are always sent.
 *
 * @since 3.0.0
 */
class MdMirror implements ActionInterface, HttpGetActionInterface
{
    /** Cache tag for all rendered mirrors — flushed via standard cache:clean. */
    public const CACHE_TAG = 'ANGEO_LLMS_MD';

    /** Separates cached tags from the cached body. */
    private const TAGS_SEPARATOR = "\0";

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
        private readonly RestrictedCatalogGuard $restrictedGuard,
        ?\Angeo\LlmsTxt\Model\Output\Signature $signature = null,
        private readonly ?\Angeo\LlmsTxt\Model\Cache\MdMirrorCacheKey $mdCacheKey = null
    ) {
        $this->signature = $signature ?? new \Angeo\LlmsTxt\Model\Output\Signature();
        $this->markdown = new MarkdownText();
    }

    /** @since 5.0.0 */
    private readonly MarkdownText $markdown;

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
                || $this->restrictedGuard->isBlocked($store)
            ) {
                return $this->notFound();
            }

            $path = trim((string) $this->request->getPathInfo(), '/');
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

            if ($requestPath === '') {
                return $this->notFound();
            }

            // ── Cache lookup (hits only; misses are never cached) ──────────
            $cacheKey = $this->buildCacheKey((int) $store->getId(), $requestPath);
            $cached   = $this->cache->load($cacheKey);
            if (is_string($cached) && str_contains($cached, self::TAGS_SEPARATOR)) {
                [$tags, $body] = explode(self::TAGS_SEPARATOR, $cached, 2);
                if ($body !== '') {
                    return $this->buildResult($body, $store, $tags);
                }
            }

            $rewrite = $this->findRewrite($requestPath, (int) $store->getId());
            if ($rewrite === null) {
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
                return $this->notFound();
            }
            $tags = $this->entityTag($rewrite);

            // Attribution signature — appended BEFORE caching so the cached
            // copy and a fresh render are byte-identical (Content-Length is
            // computed from the final string in buildResult()).
            if ($this->config->isSignatureEnabled($store)) {
                $markdown .= $this->signature->forMdMirror();
            }

            $this->cache->save(
                $tags . self::TAGS_SEPARATOR . $markdown,
                $cacheKey,
                // Entity tag too: Magento cleans cat_p_<id> / cat_c_<id> /
                // cms_p_<id> on save and delete, even when the rewrite rows
                // are already gone.
                array_values(array_filter([self::CACHE_TAG, $tags])),
                $this->config->getHttpCacheTtl()
            );

            return $this->buildResult($markdown, $store, $tags);
        } catch (\Throwable $e) {
            $this->logger->error('[Angeo LlmsTxt] MdMirror error: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
            return $this->notFound();
        }
    }

    /**
     * Resolve the request path to a canonical (non-redirect) rewrite.
     *
     * Tries the path as given, then with ".html" (the /page.md form of
     * /page.html) and with "/" (categories configured with a slash suffix).
     */
    private function findRewrite(string $requestPath, int $storeId): ?UrlRewrite
    {
        foreach ([$requestPath, $requestPath . '.html', $requestPath . '/'] as $candidate) {
            $rewrite = $this->urlFinder->findOneByData([
                UrlRewrite::REQUEST_PATH => $candidate,
                UrlRewrite::STORE_ID     => $storeId,
            ]);
            if ($rewrite !== null) {
                return (int) $rewrite->getRedirectType() === 0 ? $rewrite : null;
            }
        }

        return null;
    }

    /**
     * Full-page-cache tag of the entity, so Varnish/Fastly purge the mirror
     * together with the HTML page when the entity is saved.
     */
    private function entityTag(UrlRewrite $rewrite): string
    {
        $prefix = match ($rewrite->getEntityType()) {
            'product'  => 'cat_p_',
            'category' => 'cat_c_',
            'cms-page' => 'cms_p_',
            default    => null,
        };

        return $prefix === null ? '' : $prefix . (int) $rewrite->getEntityId();
    }

    private function buildResult(
        string $markdown,
        ?\Magento\Store\Api\Data\StoreInterface $store = null,
        string $tags = ''
    ): Raw {
        $result = $this->resultRawFactory->create();
        $result->setHeader('Content-Type', 'text/markdown; charset=utf-8', true);
        // 5.2.0: an answer to Accept: text/markdown on the HTML URL. A shared
        // cache must never keep it under that URL, so no cache tags, private.
        $negotiated = (string) $this->request->getParam(
            \Angeo\LlmsTxt\Controller\MarkdownNegotiationRouter::PARAM_NEGOTIATED
        ) === '1';
        if ($tags !== '' && !$negotiated) {
            $result->setHeader('X-Magento-Tags', $tags, true);
        }
        // llms.txt v2: the Link: header form works for non-HTML resources, so
        // an agent that fetched the mirror directly can still find the
        // llms.txt covering it without going back to the HTML page.
        if ($store !== null && $this->config->isLinkRelationsEnabled($store)) {
            $result->setHeader(
                'Link',
                sprintf('<%s/llms.txt>; rel="describedby"', rtrim($store->getBaseUrl(), '/')),
                true
            );
        }
        $result->setHeader('Content-Length', (string) strlen($markdown), true);
        $result->setHeader('Cache-Control', $negotiated ? 'private, max-age=0' : sprintf(
            'public, max-age=%d',
            $this->config->getHttpCacheTtl()
        ), true);
        if ($negotiated) {
            $result->setHeader('Vary', 'Accept', true);
        }
        $result->setHeader('X-Content-Type-Options', 'nosniff', true);
        $result->setHeader('X-Robots-Tag', 'noindex, follow', true);
        $result->setContents($markdown);
        return $result;
    }

    private function buildCacheKey(int $storeId, string $requestPath): string
    {
        // 4.1.0: the rule lives in MdMirrorCacheKey so the save observer can
        // compute the same key. Kept nullable for BC with manual instantiation.
        return ($this->mdCacheKey ?? new \Angeo\LlmsTxt\Model\Cache\MdMirrorCacheKey())
            ->forPath($storeId, $requestPath);
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

        $title = $this->markdown->inline((string) $product->getName());
        $short = $this->markdown->block(
            $this->sanitizer->sanitize((string) $product->getShortDescription(), $context)
        );
        $desc  = $this->markdown->block(
            $this->sanitizer->sanitize((string) $product->getDescription(), $context)
        );
        $product->setCustomerGroupId($context->getCustomerGroupId());
        $price = (float) $product->getFinalPrice();

        $out = "# {$title}\n\n";
        $out .= sprintf(
            "> SKU: %s · Price: %s %s\n\n",
            $this->markdown->inline((string) $product->getSku()),
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

        $title = $this->markdown->inline((string) $category->getName());
        $desc  = $this->markdown->block(
            $this->sanitizer->sanitize((string) $category->getDescription(), $context)
        );

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
        // Nor pages that are no longer assigned to this store view (a stale
        // rewrite can outlive the assignment). 0 = all store views.
        $raw      = $page instanceof \Magento\Framework\DataObject ? $page->getData('store_id') : null;
        $assigned = array_map('intval', (array) ($raw ?? []));
        $storeId  = (int) $context->getStore()->getId();
        if ($assigned !== [] && !in_array(0, $assigned, true) && !in_array($storeId, $assigned, true)) {
            return null;
        }

        $context->setShared(OutputContextInterface::SHARED_ENTITY_TYPE, 'cms-page');

        $title   = $this->markdown->inline((string) $page->getTitle());
        $content = $this->markdown->block(
            $this->sanitizer->sanitize((string) $page->getContent(), $context)
        );

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
