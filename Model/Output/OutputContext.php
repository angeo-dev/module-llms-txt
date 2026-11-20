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

    /**
     * Currency the exported prices are expressed in.
     *
     * 5.0.0: the BASE currency. Prices come from the price index
     * (final_price), which is stored in base currency and never converted.
     * Labelling them with the display/current currency was wrong whenever
     * the two differ — and in the .md mirror the current currency is the
     * visitor's choice, so one visitor could make the cached page show the
     * wrong currency to everyone.
     */
    public function getCurrencyCode(): string
    {
        if ($this->currencyCode === null) {
            $code = method_exists($this->store, 'getBaseCurrencyCode')
                ? (string) $this->store->getBaseCurrencyCode()
                : '';
            $this->currencyCode = $code !== '' ? $code : (string) $this->store->getDefaultCurrencyCode();
        }
        return $this->currencyCode;
    }

    public function getBaseUrl(): string
    {
        if ($this->baseUrl === null) {
            $this->baseUrl = rtrim(self::storefrontBaseUrl($this->store), '/');
        }
        return $this->baseUrl;
    }

    /**
     * Storefront link base URL of a store, identical from web, cron and CLI.
     *
     * 5.0.0: URL_TYPE_LINK (was URL_TYPE_WEB). WEB never carries the store
     * code, so with "Add Store Code to URLs" every URL pointed at the default
     * store. The secure flag is taken from the store's own frontend setting:
     * from CLI/cron "currently secure" is always false and produced http://.
     *
     * @since 5.0.0
     */
    public static function storefrontBaseUrl(StoreInterface $store): string
    {
        $secure = method_exists($store, 'isFrontUrlSecure') ? (bool) $store->isFrontUrlSecure() : null;

        return (string) $store->getBaseUrl(UrlInterface::URL_TYPE_LINK, $secure);
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
