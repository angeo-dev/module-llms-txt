<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Jsonl\Providers;

use Angeo\LlmsTxt\Api\Jsonl\DefaultProviderApi;
use Magento\Store\Api\Data\StoreInterface;

class StoreProvider implements DefaultProviderApi
{
    public function provide(StoreInterface $store): string
    {
        return json_encode([
                "type" => "store",
                "code" => $store->getCode(),
                "name" => $store->getName(),
                "url" => $store->getBaseUrl(),
                "currency" => $store->getCurrentCurrencyCode(),
                "locale" => $store->getLocaleCode()
            ], JSON_UNESCAPED_UNICODE) . PHP_EOL;
    }
}
