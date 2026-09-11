<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Controller\Index;

use Angeo\LlmsTxt\Model\Config;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Serves /sitemap_agentic_discovery.xml — a sitemap whose only job is to
 * declare the agent-facing files of this store.
 *
 * Why a separate sitemap rather than entries in the main one: the main sitemap
 * is a list of indexable human pages, and search engines treat unexpected
 * entries there as noise. Shopify solved it the same way, and a declared path
 * beats making an agent guess. Submit it in Search Console next to the
 * regular sitemap.
 *
 * Only files that are actually enabled and generated for the current store are
 * listed — a sitemap pointing at a 404 is worse than no sitemap.
 *
 * @since 4.0.0
 */
class AgenticSitemap implements ActionInterface, HttpGetActionInterface
{
    public function __construct(
        private readonly HttpResponse $response,
        private readonly StoreManagerInterface $storeManager,
        private readonly RawFactory $resultRawFactory,
        private readonly Config $config,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute()
    {
        try {
            $store = $this->storeManager->getStore();

            if (
                !$this->config->isEnabled($store)
                || $this->config->isStoreExcluded($store)
                || !$this->config->isAgenticSitemapEnabled($store)
            ) {
                return $this->notFound();
            }

            $baseUrl = rtrim($store->getBaseUrl(), '/');
            $urls    = [];

            if ($this->config->isAgentsMdEnabled($store)) {
                $urls[] = $baseUrl . '/agents.md';
            }
            if ($this->config->isLlmsTxtEnabled($store)) {
                $urls[] = $baseUrl . '/llms.txt';
            }
            if ($this->config->isLlmsFullTxtEnabled($store)) {
                $urls[] = $baseUrl . '/llms-full.txt';
            }
            if ($this->config->isJsonlEnabled($store)) {
                $urls[] = $baseUrl . '/llms.jsonl';
            }

            if ($urls === []) {
                return $this->notFound();
            }

            $result = $this->resultRawFactory->create();
            $result->setHeader('Content-Type', 'application/xml; charset=utf-8', true);
            $result->setHeader('X-Content-Type-Options', 'nosniff', true);
            $result->setHeader('Cache-Control', sprintf(
                'public, max-age=%d',
                $this->config->getHttpCacheTtl()
            ), true);
            $result->setContents($this->render($urls));

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('[Angeo LlmsTxt] Agentic sitemap error: ' . $e->getMessage(), [
                'exception' => $e,
            ]);

            return $this->notFound();
        }
    }

    /**
     * @param string[] $urls
     */
    private function render(array $urls): string
    {
        $xml = "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n"
            . "<urlset xmlns=\"http://www.sitemaps.org/schemas/sitemap/0.9\">\n";

        foreach ($urls as $url) {
            $xml .= "  <url>\n"
                . '    <loc>' . htmlspecialchars($url, ENT_XML1 | ENT_COMPAT, 'UTF-8') . "</loc>\n"
                . "    <changefreq>weekly</changefreq>\n"
                . "  </url>\n";
        }

        return $xml . "</urlset>\n";
    }

    private function notFound(): HttpResponse
    {
        $this->response->setHttpResponseCode(404);
        $this->response->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
        $this->response->setBody("Not found.\n");

        return $this->response;
    }
}
