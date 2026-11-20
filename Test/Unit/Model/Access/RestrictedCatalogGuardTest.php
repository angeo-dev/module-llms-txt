<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Model\Access;

use Angeo\LlmsTxt\Model\Access\RestrictedCatalogGuard;
use Angeo\LlmsTxt\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\TestCase;

class RestrictedCatalogGuardTest extends TestCase
{
    /**
     * @param array<string, bool> $modules
     * @param array<string, bool> $flags
     */
    private function guard(array $modules, array $flags, bool $allowed): RestrictedCatalogGuard
    {
        $moduleManager = $this->createMock(ModuleManager::class);
        $moduleManager->method('isEnabled')->willReturnCallback(static fn (string $m) => $modules[$m] ?? false);

        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('isSetFlag')->willReturnCallback(static fn (string $p) => $flags[$p] ?? false);

        $config = $this->createMock(Config::class);
        $config->method('isRestrictedCatalogPublishingAllowed')->willReturn($allowed);

        return new RestrictedCatalogGuard($scopeConfig, $moduleManager, $config);
    }

    public function testPlainStoreIsNotBlocked(): void
    {
        self::assertFalse($this->guard([], [], false)->isBlocked($this->createMock(StoreInterface::class)));
    }

    public function testInstalledButSwitchedOffFeatureDoesNotBlock(): void
    {
        $guard = $this->guard(['Magento_SharedCatalog' => true], [], false);
        self::assertFalse($guard->isBlocked($this->createMock(StoreInterface::class)));
    }

    public function testActiveSharedCatalogBlocksUntilMerchantAllows(): void
    {
        $modules = ['Magento_SharedCatalog' => true];
        $flags   = ['btob/website_configuration/sharedcatalog_active' => true];
        $store   = $this->createMock(StoreInterface::class);

        self::assertTrue($this->guard($modules, $flags, false)->isBlocked($store));
        self::assertSame(['B2B Shared Catalog'], $this->guard($modules, $flags, false)->activeRestrictions($store));
        self::assertFalse($this->guard($modules, $flags, true)->isBlocked($store));
    }

    public function testWebsiteRestrictionAndCatalogPermissionsAreDetected(): void
    {
        $guard = $this->guard(
            ['Magento_WebsiteRestriction' => true, 'Magento_CatalogPermissions' => true],
            ['general/restriction/is_active' => true, 'catalog/magento_catalogpermissions/enabled' => true],
            false
        );

        self::assertSame(
            ['Catalog Permissions', 'Website Restrictions'],
            $guard->activeRestrictions($this->createMock(StoreInterface::class))
        );
    }
}
