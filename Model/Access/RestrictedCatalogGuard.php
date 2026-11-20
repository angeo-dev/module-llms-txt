<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Access;

use Angeo\LlmsTxt\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\ScopeInterface;

/**
 * Detects stores whose catalog is not meant to be public.
 *
 * The module does not evaluate Adobe Commerce Catalog Permissions, B2B Shared
 * Catalogs or Website Restrictions per customer — it exports one public view
 * of the catalog. On a store that uses any of them, that export would publish
 * products, categories and prices the merchant has hidden. So such stores are
 * skipped (files removed, nothing served) unless the merchant explicitly
 * confirms in the admin that publishing the full catalog is intended.
 *
 * @since 5.0.0
 */
class RestrictedCatalogGuard
{
    /** module name => [config path that switches the feature on, label] */
    private const FEATURES = [
        'Magento_CatalogPermissions' => ['catalog/magento_catalogpermissions/enabled', 'Catalog Permissions'],
        'Magento_SharedCatalog'      => ['btob/website_configuration/sharedcatalog_active', 'B2B Shared Catalog'],
        'Magento_WebsiteRestriction' => ['general/restriction/is_active', 'Website Restrictions'],
    ];

    public function __construct(
        private readonly ScopeConfigInterface $scopeConfig,
        private readonly ModuleManager $moduleManager,
        private readonly Config $config
    ) {
    }

    /**
     * Labels of the access-control features active for this store.
     *
     * @return string[]
     */
    public function activeRestrictions(StoreInterface $store): array
    {
        $active = [];
        foreach (self::FEATURES as $module => [$path, $label]) {
            if (!$this->moduleManager->isEnabled($module)) {
                continue;
            }
            if ($this->scopeConfig->isSetFlag($path, ScopeInterface::SCOPE_STORE, $store->getId())) {
                $active[] = $label;
            }
        }

        return $active;
    }

    /**
     * True when output must be withheld for this store.
     */
    public function isBlocked(StoreInterface $store): bool
    {
        return $this->activeRestrictions($store) !== []
            && !$this->config->isRestrictedCatalogPublishingAllowed($store);
    }
}
