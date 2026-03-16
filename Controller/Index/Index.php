<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Controller\Index;

use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Store\Model\StoreManagerInterface;
use Magento\Framework\Filesystem;
use Magento\Framework\App\Filesystem\DirectoryList;

class Index implements ActionInterface, HttpGetActionInterface
{
    public function __construct(
        private readonly RawFactory $resultRawFactory,
        private readonly StoreManagerInterface $storeManager,
        private readonly Filesystem $filesystem
    ) {}

    public function execute()
    {
        $store = $this->storeManager->getStore();

        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $filePath = $directory->getAbsolutePath("llms_{$store->getCode()}.txt");

        if (!file_exists($filePath)) {
            $result = $this->resultRawFactory->create();
            $result->setHttpResponseCode(404);
            $result->setContents(__('LLMS file not generated yet.'));
            return $result;
        }

        $content = file_get_contents($filePath);

        $result = $this->resultRawFactory->create();
        $result->setHeader('Content-Type', 'text/plain', true);
        $result->setContents($content);

        return $result;
    }
}
