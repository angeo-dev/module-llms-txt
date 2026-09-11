<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model;

use Magento\Customer\Model\Group as CustomerGroup;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;
use Magento\Store\Model\Store;

/**
 * Central configuration helper for Angeo_LlmsTxt.
 *
 * All config nodes live under the angeo_llms/* tree. Reads honour standard Magento
 * scope inheritance (default → website → store) unless a specific scope is forced
 * for a setting that only makes sense at that scope (e.g. exclude_store).
 *
 * @since 3.0.0
 */
class Config
{
    /**
     * Module version, kept in sync with composer.json. Single source for the
     * CLI banner and the output signature ({@see \Angeo\LlmsTxt\Model\Output\Signature}).
     *
     * @since 3.3.0
     */
    public const MODULE_VERSION = '3.4.0';

    // ─── General ───────────────────────────────────────────────────────────
    public const XML_PATH_ENABLED            = 'angeo_llms/general/enabled';
    public const XML_PATH_EXCLUDE_STORE      = 'angeo_llms/general/exclude_store';
    public const XML_PATH_STORE_SUMMARY      = 'angeo_llms/general/store_summary';
    public const XML_PATH_SIGNATURE          = 'angeo_llms/general/signature_enabled';

    // ─── Content ───────────────────────────────────────────────────────────
    public const XML_PATH_INCLUDE_PRODUCTS   = 'angeo_llms/content/include_products';
    public const XML_PATH_INCLUDE_CATEGORIES = 'angeo_llms/content/include_categories';
    public const XML_PATH_INCLUDE_CMS        = 'angeo_llms/content/include_cms';
    public const XML_PATH_PRODUCTS_OPTIONAL  = 'angeo_llms/content/products_under_optional';
    public const XML_PATH_PRODUCT_LIMIT      = 'angeo_llms/content/product_limit';
    public const XML_PATH_EXCLUDE_OOS        = 'angeo_llms/content/exclude_out_of_stock';
    public const XML_PATH_CMS_EXCLUDE_IDS    = 'angeo_llms/content/cms_exclude_identifiers';
    public const XML_PATH_CUSTOMER_GROUP     = 'angeo_llms/content/customer_group_id';

    // ─── Formats ───────────────────────────────────────────────────────────
    public const XML_PATH_GENERATE_LLMS      = 'angeo_llms/formats/generate_llms_txt';
    public const XML_PATH_GENERATE_FULL      = 'angeo_llms/formats/generate_llms_full_txt';
    public const XML_PATH_GENERATE_JSONL     = 'angeo_llms/formats/generate_jsonl';
    public const XML_PATH_GENERATE_MD_MIRROR = 'angeo_llms/formats/generate_md_mirror';
    public const XML_PATH_GENERATE_AGENTS_MD = 'angeo_llms/formats/generate_agents_md';

    // ─── Sanitizer ─────────────────────────────────────────────────────────
    public const XML_PATH_RESOLVE_DIRECTIVES = 'angeo_llms/sanitizer/resolve_directives';
    public const XML_PATH_RESOLVE_DIRECTIVES_PRODUCTS = 'angeo_llms/sanitizer/resolve_directives_products';
    public const XML_PATH_PB_STRATEGY        = 'angeo_llms/sanitizer/page_builder_strategy';
    public const XML_PATH_PB_EXCLUDED_TYPES  = 'angeo_llms/sanitizer/page_builder_excluded_types';
    public const XML_PATH_PB_ALLOWED_TYPES   = 'angeo_llms/sanitizer/page_builder_allowed_types';

    // ─── Performance ───────────────────────────────────────────────────────
    public const XML_PATH_PAGE_SIZE          = 'angeo_llms/performance/collection_page_size';
    public const XML_PATH_GENERATION_MODE    = 'angeo_llms/performance/generation_mode';

    /**
     * Generation pipeline modes (3.2.0).
     *
     * LEGACY      — three independent generators, one catalog pass per format
     *               (pre-3.2 behavior; default). DEPRECATED: will be removed
     *               in 4.0.0, when single-pass becomes the only pipeline.
     * SINGLE_PASS — one catalog pass per store renders all enabled formats.
     */
    public const MODE_LEGACY      = 'legacy';
    public const MODE_SINGLE_PASS = 'single_pass';

    // ─── HTTP ──────────────────────────────────────────────────────────────
    public const XML_PATH_CACHE_TTL          = 'angeo_llms/http/cache_ttl_seconds';

    // ─── Cron ──────────────────────────────────────────────────────────────
    public const XML_PATH_CRON_SCHEDULE      = 'angeo_llms/cron/schedule';

    /**
     * Page Builder sanitization strategies for {@see getPageBuilderStrategy()}.
     *
     * PRESERVE — keep all Page Builder content (strip only the wrapper attributes).
     * EXCLUDE  — drop the content-types listed in {@see getPageBuilderExcludedTypes()}.
     * ALLOW    — drop everything EXCEPT the content-types in {@see getPageBuilderAllowedTypes()}.
     * STRIP    — remove ALL elements that carry a data-content-type attribute.
     */
    public const PB_STRATEGY_PRESERVE = 'preserve';
    public const PB_STRATEGY_EXCLUDE  = 'exclude';
    public const PB_STRATEGY_ALLOW    = 'allow';
    public const PB_STRATEGY_STRIP    = 'strip';

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig
    ) {
    }

    /**
     * Is the module enabled? Inherits default → website → store.
     */
    public function isEnabled(?StoreInterface $store = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_ENABLED,
            ScopeInterface::SCOPE_STORE,
            $store?->getId()
        );
    }

    /**
     * Should the given store be excluded from generation?
     *
     * Reads at store scope but inherits website / default — so a merchant with 10
     * store views per website can flip exclusion once at the website scope.
     */
    public function isStoreExcluded(StoreInterface $store): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_EXCLUDE_STORE,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );
    }

    public function getStoreSummary(StoreInterface $store): string
    {
        return trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_STORE_SUMMARY,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        ));
    }

    /**
     * Whether the attribution signature footer is appended to generated
     * markdown output (llms.txt / llms-full.txt / .md mirrors). Never applies
     * to JSONL. Default on; can be disabled per store.
     *
     * @since 3.3.0
     */
    public function isSignatureEnabled(?StoreInterface $store = null): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SIGNATURE,
            ScopeInterface::SCOPE_STORE,
            $store?->getId()
        );
    }

    public function isProductsIncluded(StoreInterface $store): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_INCLUDE_PRODUCTS,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );
    }

    public function isCategoriesIncluded(StoreInterface $store): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_INCLUDE_CATEGORIES,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );
    }

    public function isCmsIncluded(StoreInterface $store): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_INCLUDE_CMS,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );
    }

    /**
     * Whether the ## Products section should be placed under ## Optional (spec compliance).
     */
    public function areProductsUnderOptional(StoreInterface $store): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PRODUCTS_OPTIONAL,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );
    }

    /**
     * Maximum products to include in the txt output. 0 = unlimited.
     */
    public function getProductLimit(StoreInterface $store): int
    {
        return max(0, (int) $this->scopeConfig->getValue(
            self::XML_PATH_PRODUCT_LIMIT,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        ));
    }

    public function isExcludeOutOfStock(StoreInterface $store): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_EXCLUDE_OOS,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );
    }

    /**
     * @return string[]  CMS page identifiers to skip.
     */
    public function getCmsExcludedIdentifiers(StoreInterface $store): array
    {
        $value = (string) $this->scopeConfig->getValue(
            self::XML_PATH_CMS_EXCLUDE_IDS,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );

        if ($value === '') {
            return ['no-route', 'enable-cookies', 'privacy-policy-cookie-restriction-mode'];
        }

        return array_values(array_filter(array_map('trim', preg_split('/[,\n]/', $value) ?: [])));
    }

    public function getCustomerGroupId(StoreInterface $store): int
    {
        $value = $this->scopeConfig->getValue(
            self::XML_PATH_CUSTOMER_GROUP,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );

        return $value === null ? CustomerGroup::NOT_LOGGED_IN_ID : (int) $value;
    }

    public function isLlmsTxtEnabled(StoreInterface $store): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_GENERATE_LLMS,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );
    }

    public function isLlmsFullTxtEnabled(StoreInterface $store): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_GENERATE_FULL,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );
    }

    public function isJsonlEnabled(StoreInterface $store): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_GENERATE_JSONL,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );
    }

    /**
     * agents.md — operator's manual for AI agents (Shopify storefront
     * convention, May 2026). Generated by a standalone generator, independent
     * of the pipeline mode.
     *
     * @since 3.4.0
     */
    public function isAgentsMdEnabled(StoreInterface $store): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_GENERATE_AGENTS_MD,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );
    }

    public function isMdMirrorEnabled(StoreInterface $store): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_GENERATE_MD_MIRROR,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );
    }

    public function shouldResolveDirectives(StoreInterface $store): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_RESOLVE_DIRECTIVES,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );
    }

    /**
     * Whether {{block}}/{{widget}}/{{var}} directives inside PRODUCT attribute
     * content should be resolved. Default NO: product descriptions often come
     * from imported feeds (semi-trusted), and resolving directives there is a
     * template-directive-injection surface. When disabled, directives in product
     * content are stripped instead of resolved.
     *
     * @since 3.1.0
     */
    public function shouldResolveProductDirectives(StoreInterface $store): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_RESOLVE_DIRECTIVES_PRODUCTS,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );
    }

    public function getPageBuilderStrategy(StoreInterface $store): string
    {
        $value = (string) $this->scopeConfig->getValue(
            self::XML_PATH_PB_STRATEGY,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );

        return $value !== '' ? $value : self::PB_STRATEGY_PRESERVE;
    }

    /**
     * @return string[]  data-content-type values to drop when strategy = EXCLUDE.
     */
    public function getPageBuilderExcludedTypes(StoreInterface $store): array
    {
        return $this->parseCsv((string) $this->scopeConfig->getValue(
            self::XML_PATH_PB_EXCLUDED_TYPES,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        ));
    }

    /**
     * @return string[]  data-content-type values to keep when strategy = ALLOW.
     */
    public function getPageBuilderAllowedTypes(StoreInterface $store): array
    {
        return $this->parseCsv((string) $this->scopeConfig->getValue(
            self::XML_PATH_PB_ALLOWED_TYPES,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        ));
    }

    public function getCollectionPageSize(StoreInterface $store): int
    {
        $size = (int) $this->scopeConfig->getValue(
            self::XML_PATH_PAGE_SIZE,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );

        return $size > 0 ? $size : 500;
    }

    /**
     * Active generation pipeline. Global scope — mixing modes per store would
     * make concurrent runs fight over the same output files.
     *
     * @since 3.2.0
     */
    public function getGenerationMode(): string
    {
        $mode = (string) $this->scopeConfig->getValue(self::XML_PATH_GENERATION_MODE);
        return $mode === self::MODE_SINGLE_PASS ? self::MODE_SINGLE_PASS : self::MODE_LEGACY;
    }

    /**
     * @since 3.2.0
     */
    public function isSinglePassEnabled(): bool
    {
        return $this->getGenerationMode() === self::MODE_SINGLE_PASS;
    }

    public function getHttpCacheTtl(): int
    {
        $ttl = (int) $this->scopeConfig->getValue(self::XML_PATH_CACHE_TTL);
        return $ttl > 0 ? $ttl : 3600;
    }

    /**
     * Resolve store ID safely, defaulting to ADMIN_STORE_ID for global checks.
     */
    public function getDefaultStoreScopeId(): int
    {
        return Store::DEFAULT_STORE_ID;
    }

    private function parseCsv(string $value): array
    {
        if ($value === '') {
            return [];
        }
        return array_values(array_filter(array_map('trim', preg_split('/[,\n]/', $value) ?: [])));
    }
}
