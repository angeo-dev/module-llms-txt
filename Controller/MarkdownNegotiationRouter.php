<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Controller;

use Angeo\LlmsTxt\Model\Access\RestrictedCatalogGuard;
use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\Output\AcceptNegotiation;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;

/**
 * Markdown content negotiation (5.2.0).
 *
 * A GET for /shirt.html with `Accept: text/markdown` is answered with the
 * /shirt.html.md mirror; the home page with the mirror of the home CMS page.
 * Browsers are untouched: they do not prefer markdown.
 *
 * How: this router runs first (sortOrder 15, before urlrewrite at 20). It
 * never matches. When the request asks for markdown and a mirror can be
 * rendered, it rewrites the path info to "<path>.md" and returns null; the
 * module's own router (70) then hands it to the MdMirror controller, which
 * answers with `Vary: Accept` and `Cache-Control: private`. Anything it cannot
 * mirror (checkout, search, a 404) keeps its normal HTML response.
 *
 * Off by default: "Markdown Content Negotiation" under Formats, and it needs
 * "Serve /url-key.md Mirrors". On Varnish/Fastly add `Accept` to the cache
 * key (see README); the module sends `Vary: Accept` on HTML pages for that.
 *
 * @since 5.2.0
 */
class MarkdownNegotiationRouter implements RouterInterface
{
    public const PARAM_NEGOTIATED = 'angeo_md_negotiated';
    private const XML_PATH_HOME_PAGE = 'web/default/cms_home_page';

    public function __construct(
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly RestrictedCatalogGuard $restrictedGuard,
        private readonly UrlFinderInterface $urlFinder,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function match(RequestInterface $request): ?ActionInterface
    {
        if (!$request instanceof HttpRequest || !($request->isGet() || $request->isHead())
            || !AcceptNegotiation::prefersMarkdown((string) $request->getHeader('Accept'))) {
            return null;
        }
        $path = trim((string) $request->getPathInfo(), '/');
        if (str_ends_with($path, '.md') || strlen($path) > 1000) {
            return null;
        }
        try {
            $store = $this->storeManager->getStore();
            if (!$this->config->isMdNegotiationEnabled($store)
                || !$this->config->isEnabled($store)
                || $this->config->isStoreExcluded($store)
                || $this->restrictedGuard->isBlocked($store)) {
                return null;
            }
            $target = $path === '' ? self::homeIdentifier((string) $this->scopeConfig->getValue(
                self::XML_PATH_HOME_PAGE,
                ScopeInterface::SCOPE_STORE,
                $store->getId()
            )) : $path;
            if ($target === '' || !$this->hasRewrite($target, (int) $store->getId())) {
                return null;
            }
        } catch (\Throwable) {
            return null;
        }

        $request->setPathInfo('/' . $target . '.md');
        $request->setParam(self::PARAM_NEGOTIATED, '1');

        return null;
    }

    /** "home|2" (identifier|page id, as Magento stores it) → "home". */
    public static function homeIdentifier(string $value): string
    {
        return trim(explode('|', $value, 2)[0], " /\t\n");
    }

    /** Same candidates as MdMirror::findRewrite(). */
    private function hasRewrite(string $path, int $storeId): bool
    {
        foreach ([$path, $path . '.html', $path . '/'] as $candidate) {
            $rewrite = $this->urlFinder->findOneByData([
                UrlRewrite::REQUEST_PATH => $candidate,
                UrlRewrite::STORE_ID     => $storeId,
            ]);
            if ($rewrite !== null) {
                return (int) $rewrite->getRedirectType() === 0;
            }
        }

        return false;
    }
}
