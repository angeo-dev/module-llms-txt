<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Stock;

use Magento\Framework\Module\Manager as ModuleManager;
use Magento\Framework\ObjectManagerInterface;
use Magento\Store\Api\Data\StoreInterface;
use Psr\Log\LoggerInterface;

/**
 * Resolves salable status for a batch of SKUs, using Multi-Source Inventory
 * when the merchant runs it and the legacy stock index otherwise.
 *
 * Why this exists. Until 4.0.0 stock was only ever used as a filter — exclude
 * out-of-stock products — and a wrong answer merely meant a product appeared
 * or did not. 4.0.0 started publishing availability as a fact that an AI agent
 * reads and repeats to a shopper. On a store with more than one stock, the
 * legacy `cataloginventory_stock_status` index is not the source of truth for
 * a given sales channel, so that fact could be wrong. Being wrong about "in
 * stock" is worse than not saying it.
 *
 * Single-source merchants (Default Source / Default Stock — the large
 * majority) are unaffected either way: MSI keeps the legacy index in sync for
 * the default stock.
 *
 * ── On the use of ObjectManager ──────────────────────────────────────────
 * MSI is optional. Adobe Commerce and Mage-OS both allow the
 * Magento_Inventory* modules to be removed, and this module must install and
 * compile without them. Type-hinting `AreProductsSalableInterface` in a
 * constructor would make `setup:di:compile` fail by reflection on a store that
 * removed MSI, so the dependency is resolved lazily and only after the module
 * is confirmed enabled. The escape hatch is deliberately confined to this one
 * class; nothing else in the module touches ObjectManager.
 *
 * @since 4.2.0
 */
class SalableStatusResolver
{
    public const SOURCE_AUTO   = 'auto';
    public const SOURCE_LEGACY = 'legacy';

    private const MSI_MODULE            = 'Magento_InventorySalesApi';
    private const ARE_SALABLE_INTERFACE = \Magento\InventorySalesApi\Api\AreProductsSalableInterface::class;
    private const STOCK_RESOLVER        = \Magento\InventorySalesApi\Api\StockResolverInterface::class;
    private const SALES_CHANNEL         = \Magento\InventorySalesApi\Api\Data\SalesChannelInterface::class;

    /** Cache of website code => stock id for the lifetime of one run. */
    private array $stockIdCache = [];

    public function __construct(
        private readonly ObjectManagerInterface $objectManager,
        private readonly ModuleManager $moduleManager,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Whether MSI should be used for this run.
     */
    public function usesMsi(string $configuredSource): bool
    {
        if ($configuredSource === self::SOURCE_LEGACY) {
            return false;
        }

        return $this->moduleManager->isEnabled(self::MSI_MODULE)
            && interface_exists(self::ARE_SALABLE_INTERFACE)
            && interface_exists(self::STOCK_RESOLVER);
    }

    /**
     * Salable status for a batch of SKUs in one call — never one query per
     * product.
     *
     * @param string[] $skus
     * @return array<string, bool> sku => salable. Empty array means "could not
     *         determine"; the caller must fall back to the legacy index rather
     *         than publish a guess.
     */
    public function forSkus(array $skus, StoreInterface $store): array
    {
        if ($skus === []) {
            return [];
        }

        try {
            $stockId = $this->resolveStockId($store);
            if ($stockId === null) {
                return [];
            }

            /** @var object $areSalable */
            $areSalable = $this->objectManager->get(self::ARE_SALABLE_INTERFACE);
            $results = $areSalable->execute(array_values($skus), $stockId);

            $map = [];
            foreach ($results as $result) {
                $map[(string) $result->getSku()] = (bool) $result->isSalable();
            }

            return $map;
        } catch (\Throwable $e) {
            $this->logger->warning(sprintf(
                '[Angeo LlmsTxt] MSI salable lookup failed for store %s (%s) — falling back to the legacy stock index.',
                $store->getCode(),
                $e->getMessage()
            ));

            return [];
        }
    }

    /**
     * Stock assigned to the sales channel of this store's website.
     */
    private function resolveStockId(StoreInterface $store): ?int
    {
        $websiteCode = $this->websiteCode($store);
        if ($websiteCode === null) {
            return null;
        }

        if (array_key_exists($websiteCode, $this->stockIdCache)) {
            return $this->stockIdCache[$websiteCode];
        }

        /** @var object $stockResolver */
        $stockResolver = $this->objectManager->get(self::STOCK_RESOLVER);
        $stock = $stockResolver->execute(
            constant(self::SALES_CHANNEL . '::TYPE_WEBSITE'),
            $websiteCode
        );

        return $this->stockIdCache[$websiteCode] = (int) $stock->getStockId();
    }

    private function websiteCode(StoreInterface $store): ?string
    {
        try {
            if (method_exists($store, 'getWebsite')) {
                $code = $store->getWebsite()->getCode();

                return is_string($code) && $code !== '' ? $code : null;
            }
        } catch (\Throwable) {
            return null;
        }

        return null;
    }
}
