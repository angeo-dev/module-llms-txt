<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Controller;

use Angeo\LlmsTxt\Controller\Index\AgenticSitemap as AgenticSitemapController;
use Angeo\LlmsTxt\Controller\Index\Index as IndexController;
use Angeo\LlmsTxt\Controller\Index\MdMirror as MdMirrorController;
use Angeo\LlmsTxt\Model\Config;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Frontend router for AI-discovery files.
 *
 * Resolved routes:
 *   /llms.txt        → Index controller, file=llms.txt
 *   /llms-full.txt   → Index controller, file=llms-full.txt
 *   /llms.jsonl      → Index controller, file=llms.jsonl
 *   /llms-full.jsonl → Index controller, file=llms-full.jsonl (alias of .jsonl)
 *   /agents.md       → Index controller, file=agents.md (since 3.4.0)
 *   /sitemap_agentic_discovery.xml → AgenticSitemap controller (since 4.0.0)
 *   /{path}.md       → MdMirror controller (when md_mirror is enabled)
 *
 * Multi-store: Magento's store-resolution (path-based or code-based) has already
 * applied by the time this router runs; we look at the LAST path segment so the
 * file works whether the request is /llms.txt or /de/llms.txt.
 *
 * Routing precedence (3.1.0): this router is registered with sortOrder=70 —
 * AFTER the urlrewrite (20), standard (30), and CMS (60) routers. That means
 * any real merchant content whose URL happens to end in ".md" (or even a real
 * rewrite named "llms.txt") always wins; this router only claims paths that
 * would otherwise 404. Additionally, the ".md" branch is gated on the
 * md_mirror feature being enabled for the resolved store, so a disabled
 * feature never swallows requests.
 *
 * @since 3.0.0
 */
class Router implements RouterInterface
{
    public const PARAM_FILE = 'llms_file';
    public const PARAM_MD_PATH = 'md_path';

    /** @since 4.0.0 */
    private const AGENTIC_SITEMAP = 'sitemap_agentic_discovery.xml';

    private const FILE_ROUTES = [
        'llms.txt'        => true,
        'llms-full.txt'   => true,
        'llms.jsonl'      => true,
        'llms-full.jsonl' => true,
        'agents.md'       => true, // checked BEFORE the .md-mirror branch (since 3.4.0)
    ];

    public function __construct(
        private readonly ActionFactory $actionFactory,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly LoggerInterface $logger
    ) {
    }

    public function match(RequestInterface $request): mixed
    {
        $path = trim((string) $request->getPathInfo(), '/');
        if ($path === '') {
            return null;
        }

        // Last segment is the filename — handles /llms.txt and /storepath/llms.txt.
        $lastSlash = strrpos($path, '/');
        $tail = $lastSlash === false ? $path : substr($path, $lastSlash + 1);

        if ($tail === self::AGENTIC_SITEMAP) {
            $request
                ->setModuleName('llms')
                ->setControllerName('index')
                ->setActionName('agenticsitemap')
                ->setDispatched(true);

            return $this->actionFactory->create(AgenticSitemapController::class);
        }

        if (isset(self::FILE_ROUTES[$tail])) {
            $request
                ->setModuleName('llms')
                ->setControllerName('index')
                ->setActionName('index')
                ->setParam(self::PARAM_FILE, $tail)
                ->setDispatched(true);

            return $this->actionFactory->create(IndexController::class);
        }

        // .md mirror route — any otherwise-unmatched path ending in .md, but ONLY
        // when the feature is enabled for the current store. When disabled, fall
        // through (return null) so other routers / the default 404 handler keep
        // full ownership of the URL space.
        if (str_ends_with($tail, '.md') && $this->isMdMirrorActive()) {
            $request
                ->setModuleName('llms')
                ->setControllerName('index')
                ->setActionName('md')
                ->setParam(self::PARAM_MD_PATH, $path)
                ->setDispatched(true);

            return $this->actionFactory->create(MdMirrorController::class);
        }

        return null;
    }

    /**
     * Cheap config-only gate for the .md branch. The controller re-validates
     * everything; this exists purely so a disabled feature does not hijack URLs.
     */
    private function isMdMirrorActive(): bool
    {
        try {
            $store = $this->storeManager->getStore();
            return $this->config->isEnabled($store)
                && !$this->config->isStoreExcluded($store)
                && $this->config->isMdMirrorEnabled($store);
        } catch (\Throwable $e) {
            // Store resolution can fail very early in bootstrap edge cases —
            // never let routing explode; just decline the match.
            $this->logger->debug(
                '[Angeo LlmsTxt] Router store resolution failed: ' . $e->getMessage()
            );
            return false;
        }
    }
}
