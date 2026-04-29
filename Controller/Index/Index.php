<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Controller\Index;

use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\JsonlGenerator;
use Angeo\LlmsTxt\Model\LlmsGenerator;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\ResponseInterface;
use Magento\Framework\Controller\Result\Raw;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Serves all four AI content files from media/angeo/llms/:
 *
 *   /llms.txt        → llms_{store}.txt   (spec-compliant markdown)
 *   /llms-full.txt   → llms_{store}.txt   (same file — full content alias)
 *   /llms.jsonl      → llms_{store}.jsonl (line-delimited JSON for AI agents)
 *   /llms-full.jsonl → llms_{store}.jsonl (same file — full content alias)
 *
 */
class Index implements ActionInterface, HttpGetActionInterface
{
    private const FILE_MAP = [
        'llms.txt' => 'txt',
        'llms-full.txt' => 'txt',
        'llms.jsonl' => 'jsonl',
        'llms-full.jsonl' => 'jsonl',
    ];

    public function __construct(
        private readonly ResponseInterface     $response,
        private readonly StoreManagerInterface $storeManager,
        private readonly LlmsGenerator         $llmsGenerator,
        private readonly JsonlGenerator        $jsonlGenerator,
        private readonly RequestInterface      $request,
        private readonly RawFactory            $resultRawFactory,
        private readonly Config                $config,
    ) {}

    public function execute(): Raw|ResponseInterface
    {
        $store = $this->storeManager->getStore();
        $storeCode = $store->getCode();
        $llmsFile = (string)$this->request->getParam('llms_file', 'llms.txt');
        $type = self::FILE_MAP[$llmsFile] ?? 'txt';

        // Return 404 if module is disabled globally.
        if (!$this->config->isEnabled()) {
            $this->response->setHttpResponseCode(404);
            $this->response->setBody("Not found.\n");
            return $this->response;
        }

        // Return 404 immediately if the store is disabled or excluded — do not serve stale files.
        if (!$store->isActive() || $this->config->isStoreExcluded($store)) {
            $this->response->setHttpResponseCode(404);
            $this->response->setBody("Store is not available.\n");
            return $this->response;
        }

        $filePath = $type === 'jsonl'
            ? $this->jsonlGenerator->getFilePath($storeCode)
            : $this->llmsGenerator->getFilePath($storeCode);

        if (!is_file($filePath)) {
            $this->response->setHttpResponseCode(404);
            $this->response->setBody("{$llmsFile} not generated yet.\n\n");
            return $this->response;
        }

        $content = (string)file_get_contents($filePath);
        $result = $this->resultRawFactory->create();
        $result->setHeader('Content-Type', 'text/plain', true);
        $result->setContents($content);

        return $result;
    }
}
