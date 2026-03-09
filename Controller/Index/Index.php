<?php

declare(strict_types=1);

namespace Angeo\Controller\Index;

use Magento\Framework\App\Action\Action;
use Magento\Framework\App\Action\Context;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Store\Model\StoreManagerInterface;
use const Angeo\LlmsTxt\Controller\Index\BP;

class Index extends Action
{
    public function __construct(
        private readonly Context $context,
        private readonly RawFactory $resultRawFactory,
        private readonly StoreManagerInterface $storeManager
    ) {
        parent::__construct($context);
    }

    public function execute()
    {
        $store = $this->storeManager->getStore();
        $storeCode = $store->getCode();

        $filePath = BP . "/pub/media/llms_{$storeCode}.txt";

        if (!file_exists($filePath)) {
            $result = $this->resultRawFactory->create();
            $result->setHttpResponseCode(404);
            $result->setContents('LLMS file not generated yet.');
            return $result;
        }

        $content = file_get_contents($filePath);

        $result = $this->resultRawFactory->create();
        $result->setHeader('Content-Type', 'text/plain', true);
        $result->setContents($content);

        return $result;
    }
}
