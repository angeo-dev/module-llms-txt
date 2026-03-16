<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Api\Llms;

use Magento\Store\Api\Data\StoreInterface;

interface DefaultProviderApi
{
    public function provide(StoreInterface $store): string;
}
