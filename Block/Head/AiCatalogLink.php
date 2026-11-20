<?php
declare(strict_types=1);

namespace Angeo\LlmsTxt\Block\Head;

use Angeo\LlmsTxt\Model\Access\RestrictedCatalogGuard;
use Angeo\LlmsTxt\Model\AiCatalog\CatalogBuilder;
use Angeo\LlmsTxt\Model\Config;
use Magento\Framework\View\Element\Template;
use Magento\Framework\View\Element\Template\Context;
use Magento\Store\Model\StoreManagerInterface;

/**
 * <link rel="ai-catalog" href="/.well-known/ai-catalog.json"> on every page
 * (5.1.0), the way the AI Catalog spec lets a site point agents at its
 * catalog. Rendered only when the catalog would actually be served, so the
 * link never leads to a 404.
 */
class AiCatalogLink extends Template
{
    public function __construct(
        Context $context,
        private readonly Config $config,
        private readonly StoreManagerInterface $storeManager,
        private readonly CatalogBuilder $builder,
        private readonly RestrictedCatalogGuard $restrictedGuard,
        array $data = []
    ) {
        parent::__construct($context, $data);
    }

    public function getCatalogUrl(): ?string
    {
        try {
            $store = $this->storeManager->getStore();
            if (!$this->config->isEnabled($store) || $this->config->isStoreExcluded($store)
                || $this->restrictedGuard->isBlocked($store) || !$this->config->isAiCatalogEnabled($store)
                || $this->builder->build($store) === null) {
                return null;
            }
            $base = (string) parse_url((string) $store->getBaseUrl(), PHP_URL_SCHEME) . '://'
                . (string) parse_url((string) $store->getBaseUrl(), PHP_URL_HOST);
            $port = parse_url((string) $store->getBaseUrl(), PHP_URL_PORT);

            return $base . ($port ? ':' . $port : '') . '/.well-known/ai-catalog.json';
        } catch (\Throwable) {
            return null;
        }
    }
}
