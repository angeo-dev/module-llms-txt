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
    public const MODULE_VERSION = '4.3.0';

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
    public const XML_PATH_LINK_RELATIONS     = 'angeo_llms/formats/emit_link_relations';
    public const XML_PATH_LINK_TO_MD         = 'angeo_llms/formats/link_to_md';
    public const XML_PATH_AGENTIC_SITEMAP    = 'angeo_llms/formats/generate_agentic_sitemap';

    // ─── Product data (4.0.0) ──────────────────────────────────────────────
    public const XML_PATH_PD_ATTRIBUTES      = 'angeo_llms/product_data/export_attributes';
    public const XML_PATH_PD_AVAILABILITY    = 'angeo_llms/product_data/include_availability';
    public const XML_PATH_PD_IMAGE           = 'angeo_llms/product_data/include_image';
    public const XML_PATH_PD_BRAND           = 'angeo_llms/product_data/brand_attribute';
    public const XML_PATH_PD_STOCK_SOURCE    = 'angeo_llms/product_data/stock_source';

    // ─── agents.md (4.0.0) ─────────────────────────────────────────────────
    public const XML_PATH_AGENTS_SHIPPING    = 'angeo_llms/agents/page_shipping';
    public const XML_PATH_AGENTS_RETURNS     = 'angeo_llms/agents/page_returns';
    public const XML_PATH_AGENTS_PRIVACY     = 'angeo_llms/agents/page_privacy';
    public const XML_PATH_AGENTS_TERMS       = 'angeo_llms/agents/page_terms';
    public const XML_PATH_AGENTS_ABOUT       = 'angeo_llms/agents/page_about';
    public const XML_PATH_AGENTS_SUPPORT     = 'angeo_llms/agents/support_url';

    // ─── Sanitizer ─────────────────────────────────────────────────────────
    public const XML_PATH_RESOLVE_DIRECTIVES = 'angeo_llms/sanitizer/resolve_directives';
    public const XML_PATH_RESOLVE_DIRECTIVES_PRODUCTS = 'angeo_llms/sanitizer/resolve_directives_products';
    public const XML_PATH_PB_STRATEGY        = 'angeo_llms/sanitizer/page_builder_strategy';
    public const XML_PATH_PB_EXCLUDED_TYPES  = 'angeo_llms/sanitizer/page_builder_excluded_types';
    public const XML_PATH_PB_ALLOWED_TYPES   = 'angeo_llms/sanitizer/page_builder_allowed_types';

    // ─── Performance ───────────────────────────────────────────────────────
    public const XML_PATH_PAGE_SIZE          = 'angeo_llms/performance/collection_page_size';
    public const XML_PATH_SKIP_UNCHANGED     = 'angeo_llms/performance/skip_unchanged';
    public const XML_PATH_INVALIDATE_ON_SAVE = 'angeo_llms/performance/invalidate_mirror_on_save';

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
     * Whether the products section is nested under `## Optional`.
     *
     * 4.0.0 defaults this to NO. llms.txt v2 (August 2026) removed the
     * mechanical meaning of `## Optional`: it no longer tells any tool what to
     * drop, it is just a convention for secondary links. Products are the
     * primary content of a store, so they belong in a section of their own.
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
     * Emit llms.txt v2 link relations (`rel="alternate" type="text/markdown"`
     * and `rel="describedby"`) in the storefront <head> and as HTTP `Link:`
     * headers on the markdown mirrors.
     *
     * @since 4.0.0
     */
    public function isLinkRelationsEnabled(StoreInterface $store): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_LINK_RELATIONS,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );
    }

    /**
     * Whether entity links inside llms.txt / llms-full.txt point at the
     * markdown mirror instead of the HTML page. llms.txt v2 asks for links to
     * LLM-friendly content; only meaningful when mirrors are served.
     *
     * @since 4.0.0
     */
    public function isLinkToMdEnabled(StoreInterface $store): bool
    {
        return $this->isMdMirrorEnabled($store)
            && $this->scopeConfig->isSetFlag(
                self::XML_PATH_LINK_TO_MD,
                ScopeInterface::SCOPE_STORE,
                $store->getId()
            );
    }

    /**
     * Serve /sitemap_agentic_discovery.xml — a small sitemap whose only job is
     * to declare agents.md and llms.txt so agents do not have to guess.
     *
     * @since 4.0.0
     */
    public function isAgenticSitemapEnabled(StoreInterface $store): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_AGENTIC_SITEMAP,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );
    }

    /**
     * Extra product attribute codes exported to llms-full.txt and JSONL.
     *
     * @return string[]
     * @since 4.0.0
     */
    public function getExportAttributes(StoreInterface $store): array
    {
        $codes = $this->parseCsv((string) $this->scopeConfig->getValue(
            self::XML_PATH_PD_ATTRIBUTES,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        ));

        $brand = $this->getBrandAttribute($store);
        if ($brand !== '' && !in_array($brand, $codes, true)) {
            $codes[] = $brand;
        }

        return $codes;
    }

    /** @since 4.0.0 */
    public function isAvailabilityIncluded(StoreInterface $store): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PD_AVAILABILITY,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );
    }

    /** @since 4.0.0 */
    public function isImageIncluded(StoreInterface $store): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_PD_IMAGE,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );
    }

    /**
     * Where salable status is read from: `auto` (MSI when installed, legacy
     * otherwise) or `legacy`.
     *
     * @since 4.2.0
     */
    public function getStockSource(StoreInterface $store): string
    {
        $value = (string) $this->scopeConfig->getValue(
            self::XML_PATH_PD_STOCK_SOURCE,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );

        return $value !== '' ? $value : 'auto';
    }

    /** @since 4.0.0 */
    public function getBrandAttribute(StoreInterface $store): string
    {
        return trim((string) $this->scopeConfig->getValue(
            self::XML_PATH_PD_BRAND,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        ));
    }

    /**
     * Policy / company links surfaced in agents.md. Each value is either an
     * absolute URL or a path relative to the store base URL (a CMS page
     * identifier such as `shipping-policy` works as-is).
     *
     * @return array<string, string>  label => raw configured value
     * @since 4.0.0
     */
    public function getAgentsPageLinks(StoreInterface $store): array
    {
        $map = [
            'Delivery' => self::XML_PATH_AGENTS_SHIPPING,
            'Returns'  => self::XML_PATH_AGENTS_RETURNS,
            'Privacy'  => self::XML_PATH_AGENTS_PRIVACY,
            'Terms'    => self::XML_PATH_AGENTS_TERMS,
            'About'    => self::XML_PATH_AGENTS_ABOUT,
            'Support'  => self::XML_PATH_AGENTS_SUPPORT,
        ];

        $links = [];
        foreach ($map as $label => $path) {
            $value = trim((string) $this->scopeConfig->getValue(
                $path,
                ScopeInterface::SCOPE_STORE,
                $store->getId()
            ));
            if ($value !== '') {
                $links[$label] = $value;
            }
        }

        return $links;
    }

    /**
     * Skip a store's catalog pass when nothing relevant changed since its last
     * successful run.
     *
     * Off by default, and that is deliberate: detection reads entity
     * timestamps, which stock movements and catalog price rules do not touch.
     * See {@see \Angeo\LlmsTxt\Model\Pipeline\ChangeDetector}.
     *
     * @since 4.1.0
     */
    public function isSkipUnchangedEnabled(StoreInterface $store): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_SKIP_UNCHANGED,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );
    }

    /**
     * Drop an entity's cached markdown mirror when it is saved or deleted.
     *
     * @since 4.1.0
     */
    public function isInvalidateMirrorOnSaveEnabled(StoreInterface $store): bool
    {
        return $this->scopeConfig->isSetFlag(
            self::XML_PATH_INVALIDATE_ON_SAVE,
            ScopeInterface::SCOPE_STORE,
            $store->getId()
        );
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
