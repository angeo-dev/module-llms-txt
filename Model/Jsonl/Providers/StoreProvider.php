<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Jsonl\Providers;

use Angeo\LlmsTxt\Api\ProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;

class StoreProvider implements ProviderInterface
{
    private const XML_PATH_LOCALE = 'general/locale/code';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {}

    public function provide(StoreInterface $store): string
    {
        // Bug fix: use URL_TYPE_WEB to always get frontend URL (not admin URL in cron/CLI)
        $url = $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB);

        // Bug fix: getLocaleCode() returns null — read from store-scoped config
        $locale = str_replace('_', '-', (string) $this->scopeConfig->getValue(
            self::XML_PATH_LOCALE,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        ));

        return json_encode([
            'type'           => 'store',
            'code'           => $store->getCode(),
            'name'           => $store->getName(),
            'url'            => $url,
            'currency'       => $store->getCurrentCurrencyCode(),
            'locale'         => $locale,
            'embedding_text' => $store->getName() . ' ' . $url,
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }
}
