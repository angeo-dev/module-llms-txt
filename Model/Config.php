<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Module configuration helper.
 */
class Config
{
    private const XML_PATH_ENABLED         = 'angeo_llms/general/enabled';
    private const XML_PATH_EXCLUDE_STORE   = 'angeo_llms/general/exclude_store';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {}

    /**
     * Is the module enabled globally?
     */
    public function isEnabled(): bool
    {
        return $this->scopeConfig->isSetFlag(self::XML_PATH_ENABLED);
    }

    /**
     * Should this store be excluded from llms.txt / JSONL generation?
     */
    public function isStoreExcluded(StoreInterface $store): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_EXCLUDE_STORE,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );
    }
}
