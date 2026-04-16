<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Controller;

use Angeo\LlmsTxt\Controller\Index\Index as IndexController;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;

/**
 * Routes all four AI content URLs to the Index controller:
 *
 *   /llms.txt        — spec-compliant markdown (text/plain)
 *   /llms-full.txt   — same file, full-content alias (text/plain)
 *   /llms.jsonl      — JSONL for vector indexing (application/x-ndjson)
 *   /llms-full.jsonl — same file, full-content alias (application/x-ndjson)
 *
 * The matched path is passed as the 'llms_file' request param so the
 * controller can select the correct generator and Content-Type.
 */
class Router implements RouterInterface
{
    private const ROUTES = [
        'llms.txt'        => 'llms.txt',
        'llms-full.txt'   => 'llms-full.txt',
        'llms.jsonl'      => 'llms.jsonl',
        'llms-full.jsonl' => 'llms-full.jsonl',
    ];

    public function __construct(
        private readonly ActionFactory $actionFactory
    ) {}

    public function match(RequestInterface $request): mixed
    {
        $path = trim($request->getPathInfo(), '/');

        if (!isset(self::ROUTES[$path])) {
            return null;
        }

        $request
            ->setModuleName('llms')
            ->setControllerName('index')
            ->setActionName('index')
            ->setParam('llms_file', $path)
            ->setDispatched(true);

        return $this->actionFactory->create(IndexController::class);
    }
}
