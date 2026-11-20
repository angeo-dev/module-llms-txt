<?php
declare(strict_types=1);

namespace Angeo\LlmsTxt\Controller\Index;

use Angeo\LlmsTxt\Model\Access\RestrictedCatalogGuard;
use Angeo\LlmsTxt\Model\AiCatalog\CatalogBuilder;
use Angeo\LlmsTxt\Model\Config;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * GET /.well-known/ai-catalog.json (5.1.0).
 *
 * 404 when the module or the catalog is off for the store, the store is
 * excluded or restricted, or no module offered an entry.
 */
class AiCatalog implements ActionInterface, HttpGetActionInterface
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly RawFactory $resultRawFactory,
        private readonly Config $config,
        private readonly CatalogBuilder $builder,
        private readonly RequestInterface $request,
        private readonly RestrictedCatalogGuard $restrictedGuard,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute()
    {
        try {
            $store = $this->storeManager->getStore();
            if (trim((string) $this->request->getPathInfo(), '/') !== '.well-known/ai-catalog.json'
                || !$this->config->isEnabled($store)
                || $this->config->isStoreExcluded($store)
                || $this->restrictedGuard->isBlocked($store)
                || !$this->config->isAiCatalogEnabled($store)) {
                return $this->notFound();
            }
            $catalog = $this->builder->build($store);
            if ($catalog === null) {
                return $this->notFound();
            }

            $result = $this->resultRawFactory->create();
            $result->setHeader('Content-Type', CatalogBuilder::MEDIA_TYPE, true);
            $result->setHeader('X-Content-Type-Options', 'nosniff', true);
            $result->setHeader('Access-Control-Allow-Origin', '*', true);
            $result->setHeader('Cache-Control', sprintf('public, max-age=%d', $this->config->getHttpCacheTtl()), true);
            $result->setContents((string) json_encode($catalog, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

            return $result;
        } catch (\Throwable $e) {
            $this->logger->error('[Angeo LlmsTxt] AI Catalog error: ' . $e->getMessage());

            return $this->notFound();
        }
    }

    private function notFound(): Raw
    {
        $result = $this->resultRawFactory->create();
        $result->setHttpResponseCode(404);
        $result->setHeader('Content-Type', 'text/plain; charset=utf-8', true);
        $result->setContents('Not found');

        return $result;
    }
}
