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
    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {}

    public function provide(StoreInterface $store): string
    {
        $name = $store->getName();
        $url  = rtrim((string) $store->getBaseUrl(), '/');

        // H1 title — required by llmstxt.org spec
        $output  = "# {$name}\n\n";

        // Store metadata as detail lines
        $output .= "> Store URL: {$url}\n";
        $output .= "> Currency: {$store->getCurrentCurrencyCode()}\n";
        $output .= "> Locale: {$store->getLocaleCode()}\n";
        $output .= "\n";

        return $output;
    }
}
