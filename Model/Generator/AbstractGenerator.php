<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Generator;

use Angeo\LlmsTxt\Api\ProviderInterface;
use Angeo\LlmsTxt\Model\Config;
use Magento\Framework\App\Area;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Framework\View\DesignInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractGenerator
{
    /** @param ProviderInterface[] $providers */
    public function __construct(
        protected readonly StoreManagerInterface $storeManager,
        protected readonly Filesystem            $filesystem,
        protected readonly LoggerInterface       $logger,
        protected readonly Config                $config,
        protected readonly Emulation             $emulation,
        protected readonly DesignInterface       $viewDesign,
        protected readonly array                 $providers = [],
    ) {}

    /**
     * File extension for this generator's output: 'txt' or 'jsonl'.
     */
    abstract protected function getExtension(): string;

    /**
     * Subdirectory under media/ where files are written.
     */
    protected function getSubDir(): string
    {
        return 'angeo/llms';
    }

    /**
     * Generate files for all active stores (or a single store if storeCode given).
     *
     * @throws FileSystemException
     */
    public function generate(?string $storeCode = null): void
    {
        if (!$this->config->isEnabled()) {
            $this->logger->info('[Angeo LlmsTxt] Module is disabled — generation skipped.');
            return;
        }

        $stores = $storeCode
            ? [$this->storeManager->getStore($storeCode)]
            : $this->storeManager->getStores();

        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $directory->create($this->getSubDir());

        foreach ($stores as $store) {
            if (!$store->isActive() || $this->config->isStoreExcluded($store)) {
                $this->deleteStaleFile($store, $directory);

                if (!$store->isActive()) {
                    continue;
                }

                $this->logger->info(sprintf(
                    '[Angeo LlmsTxt] Skipping store %s (excluded in config)',
                    $store->getCode()
                ));
                continue;
            }

            $emulated = false;
            try {
                // Ensure design area is initialized before emulation.
                // In cron context viewDesign->getArea() returns null which causes
                // storeCurrentEnvironmentInfo() to save null initialDesign,
                // making _restoreInitialDesign() fail with TypeError on stop.
                if (!$this->viewDesign->getArea()) {
                    $this->viewDesign->setArea(Area::AREA_FRONTEND);
                }

                $this->emulation->startEnvironmentEmulation(
                    (int) $store->getId(),
                    Area::AREA_FRONTEND,
                    true
                );
                $emulated = true;
                $this->generateForStore($store, $directory);
            } catch (\Throwable $e) {
                $this->logger->error(sprintf(
                    '[Angeo LlmsTxt] Failed to generate %s for store %s: %s',
                    $this->getExtension(),
                    $store->getCode(),
                    $e->getMessage()
                ));
            } finally {
                if ($emulated) {
                    $this->emulation->stopEnvironmentEmulation();
                }
            }
        }
    }

    /**
     * Generate and write file for a single store.
     */
    protected function generateForStore(StoreInterface $store, $directory): void
    {
        $output = '';

        foreach ($this->providers as $provider) {
            try {
                $output .= $provider->provide($store);
            } catch (\Throwable $e) {
                $this->logger->warning(sprintf(
                    '[Angeo LlmsTxt] Provider %s failed for store %s: %s',
                    get_class($provider),
                    $store->getCode(),
                    $e->getMessage()
                ));
            }
        }

        if (empty(trim($output))) {
            return;
        }

        $relativePath = $this->getSubDir() . "/llms_{$store->getCode()}.{$this->getExtension()}";

        $stream = $directory->openFile($relativePath, 'w');
        $stream->lock();
        $stream->write($output);
        $stream->unlock();
        $stream->close();

        $this->logger->info(sprintf(
            '[Angeo LlmsTxt] Generated %s (%d bytes) for store %s',
            $relativePath,
            strlen($output),
            $store->getCode()
        ));
    }

    /**
     * Returns the absolute path of the generated file for a store.
     */
    public function getFilePath(string $storeCode): string
    {
        $directory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        return $directory->getAbsolutePath(
            $this->getSubDir() . "/llms_{$storeCode}.{$this->getExtension()}"
        );
    }

    /**
     * Delete the generated file for a store if it exists (called when store is disabled or excluded).
     */
    protected function deleteStaleFile(StoreInterface $store, $directory): void
    {
        $relativePath = $this->getSubDir() . "/llms_{$store->getCode()}.{$this->getExtension()}";

        if (!$directory->isExist($relativePath)) {
            return;
        }

        try {
            $directory->delete($relativePath);
            $this->logger->info(sprintf(
                '[Angeo LlmsTxt] Deleted stale file %s for store %s',
                $relativePath,
                $store->getCode()
            ));
        } catch (\Throwable $e) {
            $this->logger->warning(sprintf(
                '[Angeo LlmsTxt] Could not delete stale file %s: %s',
                $relativePath,
                $e->getMessage()
            ));
        }
    }
}
