<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Jsonl\Providers;

use Angeo\LlmsTxt\Api\ProviderInterface;
use Magento\Store\Api\Data\StoreInterface;

class StoreProvider implements ProviderInterface
{
    public function provide(StoreInterface $store): string
    {
        return json_encode([
            'type'           => 'store',
            'code'           => $store->getCode(),
            'name'           => $store->getName(),
            'url'            => $store->getBaseUrl(),
            'currency'       => $store->getCurrentCurrencyCode(),
            'locale'         => $store->getLocaleCode(),
            'embedding_text' => $store->getName() . ' ' . $store->getBaseUrl(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) . "\n";
    }
}
