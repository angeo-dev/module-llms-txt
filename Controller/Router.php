<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Controller;

use Angeo\LlmsTxt\Api\DefaultConfigApi;
use Angeo\LlmsTxt\Controller\Index\Index as IndexController;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\App\RouterInterface;

class Router implements RouterInterface
{
    public function __construct(
        private readonly ActionFactory $actionFactory
    ) {}

    public function match(RequestInterface $request)
    {
        $path = trim($request->getPathInfo(), '/');

        if ($path === DefaultConfigApi::LLMS_TXT) {
            $request->setModuleName('llms')
                ->setControllerName('index')
                ->setActionName('index')
                ->setDispatched(true);

            return $this->actionFactory->create(IndexController::class);
        }
    }
}
