<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Generator;

use Angeo\LlmsTxt\Api\ProviderInterface;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

abstract class AbstractGenerator
{
    /** @param ProviderInterface[] $providers */
    public function __construct(
        protected readonly StoreManagerInterface $storeManager,
        protected readonly Filesystem $filesystem,
        protected readonly LoggerInterface $logger,
        protected readonly array $providers = [],
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
        $stores = $storeCode
            ? [$this->storeManager->getStore($storeCode)]
            : $this->storeManager->getStores();

        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $directory->create($this->getSubDir());

        foreach ($stores as $store) {
            if (!$store->isActive()) {
                continue;
            }

            try {
                $this->generateForStore($store, $directory);
            } catch (\Throwable $e) {
                $this->logger->error(sprintf(
                    '[Angeo LlmsTxt] Failed to generate %s for store %s: %s',
                    $this->getExtension(),
                    $store->getCode(),
                    $e->getMessage()
                ));
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
}
