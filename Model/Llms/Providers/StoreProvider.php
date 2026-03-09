<?php

declare(strict_types=1);

namespace Angeo\Model\Llms\Providers;

use Angeo\Api\Llms\DefaultProviderApi;
use Magento\Store\Api\Data\StoreInterface;

class StoreProvider implements DefaultProviderApi
{
    public function provide(StoreInterface $store): string
    {
        $output = "### STORE ###\n";
        $output .= "Name: {$store->getName()}\n";
        $output .= "Code: {$store->getCode()}\n";
        $output .= "URL: {$store->getBaseUrl()}\n";
        $output .= "Currency: {$store->getCurrentCurrencyCode()}\n";
        $output .= "Locale: {$store->getLocaleCode()}\n\n";

        return $output;
    }
}
