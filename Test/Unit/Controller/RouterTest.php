<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Controller;

use Angeo\LlmsTxt\Controller\Router;
use Angeo\LlmsTxt\Model\Config;
use Magento\Framework\App\ActionFactory;
use Magento\Framework\App\ActionInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class RouterTest extends TestCase
{
    private function router(bool $mirror = false): Router
    {
        $actionFactory = $this->createMock(ActionFactory::class);
        $actionFactory->method('create')->willReturn($this->createMock(ActionInterface::class));

        $config = $this->createMock(Config::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('isStoreExcluded')->willReturn(false);
        $config->method('isMdMirrorEnabled')->willReturn($mirror);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($this->createMock(StoreInterface::class));

        return new Router($actionFactory, $config, $storeManager, $this->createMock(LoggerInterface::class));
    }

    private function request(string $path): HttpRequest
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getPathInfo')->willReturn($path);
        foreach (['setModuleName', 'setControllerName', 'setActionName', 'setParam', 'setDispatched'] as $m) {
            $request->method($m)->willReturnSelf();
        }
        return $request;
    }

    /**
     * @dataProvider rootFiles
     */
    public function testDiscoveryFilesMatchAtRoot(string $path): void
    {
        self::assertNotNull($this->router()->match($this->request($path)));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function rootFiles(): array
    {
        return [
            'llms.txt'   => ['/llms.txt'],
            'full'       => ['/llms-full.txt'],
            'jsonl'      => ['/llms.jsonl'],
            'full jsonl' => ['/llms-full.jsonl'],
            'agents'     => ['/agents.md'],
            'sitemap'    => ['/sitemap_agentic_discovery.xml'],
        ];
    }

    /**
     * 5.0.0: /any/deep/path/llms.txt no longer becomes a second URL of the file.
     */
    public function testDiscoveryFilesDoNotMatchBelowRoot(): void
    {
        self::assertNull($this->router()->match($this->request('/a/b/llms.txt')));
        self::assertNull($this->router()->match($this->request('/customer/sitemap_agentic_discovery.xml')));
    }

    public function testMirrorPathsOnlyWhenMirrorEnabled(): void
    {
        self::assertNull($this->router(false)->match($this->request('/women/shirt.html.md')));
        self::assertNotNull($this->router(true)->match($this->request('/women/shirt.html.md')));
    }
}
