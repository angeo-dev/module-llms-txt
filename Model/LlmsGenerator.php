<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Model;

use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File;
use Magento\Store\Model\StoreManagerInterface;

class LlmsGenerator
{
    public function __construct(
        private readonly StoreManagerInterface $storeManager,
        private readonly Filesystem $filesystem,
        private readonly File $fileIo,
        private readonly array $providers = []
    ) {}

    public function generate(): void
    {
        $stores = $this->storeManager->getStores();
        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);

        $output = '';
        foreach ($stores as $store) {

            if (!$store->isActive()) {
                continue;
            }

            foreach ($this->providers as $provider) {
                $output .= $provider->provide($store);
            }

            if (empty($output)) {
                continue;
            }

            $filePath = $directory->getAbsolutePath("llms_{$store->getCode()}.txt");
            $this->fileIo->write($filePath, $output, 0775);
        }
    }
}
