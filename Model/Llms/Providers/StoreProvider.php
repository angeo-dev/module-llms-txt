<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Llms\Providers;

use Angeo\LlmsTxt\Api\ProviderInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Generates the required H1 title and store metadata header.
 *
 * Per llmstxt.org spec — the FIRST line of llms.txt MUST be:
 *   # Store Name
 *
 * This provider MUST be the first in di.xml providers array.
 */
class StoreProvider implements ProviderInterface
{
    private const XML_PATH_LOCALE = 'general/locale/code';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {}

    public function provide(StoreInterface $store): string
    {
        $name = $store->getName();

        // Bug fix: getBaseUrl() without type returns admin URL when called from cron/CLI.
        // Explicitly request the frontend base URL.
        $url = rtrim(
            (string) $store->getBaseUrl(\Magento\Framework\UrlInterface::URL_TYPE_WEB),
            '/'
        );

        // Bug fix: getLocaleCode() returns null in many Magento versions.
        // Read locale directly from store-scoped config.
        $locale = str_replace('_', '-', (string) $this->scopeConfig->getValue(
            self::XML_PATH_LOCALE,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        ));

        // H1 title — required by llmstxt.org spec
        $output  = "# {$name}\n\n";

        // Store metadata as detail lines
        $output .= "> Store URL: {$url}\n";
        $output .= "> Currency: {$store->getCurrentCurrencyCode()}\n";
        $output .= "> Locale: {$locale}\n";
        $output .= "\n";

        return $output;
    }
}
