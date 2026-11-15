<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Output;

use Angeo\LlmsTxt\Api\OutputContextInterface;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Concrete {@see OutputContextInterface} implementation.
 *
 * Instances are created per-generation by {@see OutputContextFactory} — never
 * inject this class directly; always go through the factory so the locale /
 * currency / base URL are resolved correctly under frontend emulation.
 *
 * @since 3.0.0
 */
class OutputContext implements OutputContextInterface
{
    private const XML_PATH_LOCALE = 'general/locale/code';

    /** @var array<string, mixed> */
    private array $shared = [];

    private ?string $localeCode = null;
    private ?string $currencyCode = null;
    private ?string $baseUrl = null;

    public function __construct(
        private readonly StoreInterface $store,
        private readonly string $format,
        private readonly string $verbosity,
        private readonly int $customerGroupId,
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    public function getStore(): StoreInterface
    {
        return $this->store;
    }

    public function getFormat(): string
    {
        return $this->format;
    }

    public function getVerbosity(): string
    {
        return $this->verbosity;
    }

    public function getCustomerGroupId(): int
    {
        return $this->customerGroupId;
    }

    public function getLocaleCode(): string
    {
        if ($this->localeCode === null) {
            // getLocaleCode() returns null on many Magento versions; read scoped config directly.
            $raw = (string) $this->scopeConfig->getValue(
                self::XML_PATH_LOCALE,
                ScopeInterface::SCOPE_STORE,
                $this->store->getId()
            );
            // Normalize to BCP 47: en_US → en-US
            $this->localeCode = $raw !== '' ? str_replace('_', '-', $raw) : 'en-US';
        }
        return $this->localeCode;
    }

    public function getCurrencyCode(): string
    {
        if ($this->currencyCode === null) {
            $code = (string) $this->store->getCurrentCurrencyCode();
            $this->currencyCode = $code !== '' ? $code : (string) $this->store->getDefaultCurrencyCode();
        }
        return $this->currencyCode;
    }

    public function getBaseUrl(): string
    {
        if ($this->baseUrl === null) {
            $this->baseUrl = rtrim(
                (string) $this->store->getBaseUrl(UrlInterface::URL_TYPE_LINK, $this->store->isFrontUrlSecure()),
                '/'
            );
        }
        return $this->baseUrl;
    }

    public function getShared(string $key): mixed
    {
        return $this->shared[$key] ?? null;
    }

    public function setShared(string $key, mixed $value): void
    {
        $this->shared[$key] = $value;
    }
}
