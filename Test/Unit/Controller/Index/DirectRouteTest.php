<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Controller\Index;

use Angeo\LlmsTxt\Api\SanitizerInterface;
use Angeo\LlmsTxt\Controller\Index\Index;
use Angeo\LlmsTxt\Controller\Index\MdMirror;
use Angeo\LlmsTxt\Model\Access\RestrictedCatalogGuard;
use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\Output\FilePathResolver;
use Angeo\LlmsTxt\Model\Output\OutputContextFactory;
use Magento\Catalog\Api\CategoryRepositoryInterface;
use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Cms\Api\PageRepositoryInterface;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\App\Request\Http as HttpRequest;
use Magento\Framework\App\Response\Http as HttpResponse;
use Magento\Framework\Controller\Result\RawFactory;
use Magento\Framework\Filesystem;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

require_once __DIR__ . '/../../_files/generated_factories.php';

/**
 * 5.0.0: the standard-router route (/llms/index/…) must not serve anything.
 * The controllers take the file / page from the URL path, not from a
 * request parameter an attacker can set in the query string.
 */
class DirectRouteTest extends TestCase
{
    private function store(): Store
    {
        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $store->method('getCode')->willReturn('default');
        $store->method('isActive')->willReturn(true);
        return $store;
    }

    private function config(): Config
    {
        $config = $this->createMock(Config::class);
        $config->method('isEnabled')->willReturn(true);
        $config->method('isStoreExcluded')->willReturn(false);
        $config->method('isMdMirrorEnabled')->willReturn(true);
        return $config;
    }

    private function response(): HttpResponse
    {
        $response = $this->createMock(HttpResponse::class);
        $response->expects(self::once())->method('setHttpResponseCode')->with(404);
        return $response;
    }

    public function testIndexIgnoresFileParameterOnDirectRoute(): void
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getParam')->willReturn('llms.jsonl');
        $request->method('getPathInfo')->willReturn('/llms/index/index');

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($this->store());

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->expects(self::never())->method('getDirectoryRead');

        $controller = new Index(
            $this->response(),
            $request,
            $storeManager,
            new FilePathResolver(),
            $this->createMock(RawFactory::class),
            $this->config(),
            $filesystem,
            $this->createMock(LoggerInterface::class),
            $this->createMock(RestrictedCatalogGuard::class)
        );

        $controller->execute();
    }

    public function testMdMirrorIgnoresPathParameterOnDirectRoute(): void
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getParam')->willReturn('women/shirt.html.md');
        $request->method('getPathInfo')->willReturn('/llms/index/mdmirror');

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($this->store());

        $urlFinder = $this->createMock(UrlFinderInterface::class);
        $urlFinder->expects(self::never())->method('findOneByData');
        $cache = $this->createMock(CacheInterface::class);
        $cache->expects(self::never())->method('save');

        $controller = new MdMirror(
            $this->response(),
            $request,
            $storeManager,
            $this->createMock(RawFactory::class),
            $this->config(),
            $urlFinder,
            $this->createMock(ProductRepositoryInterface::class),
            $this->createMock(CategoryRepositoryInterface::class),
            $this->createMock(PageRepositoryInterface::class),
            $this->createMock(SanitizerInterface::class),
            $this->createMock(OutputContextFactory::class),
            $cache,
            $this->createMock(LoggerInterface::class),
            $this->createMock(RestrictedCatalogGuard::class)
        );

        $controller->execute();
    }

    public function testMdMirrorDoesNotCacheMisses(): void
    {
        $request = $this->createMock(HttpRequest::class);
        $request->method('getPathInfo')->willReturn('/random-path-123.md');

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStore')->willReturn($this->store());

        $urlFinder = $this->createMock(UrlFinderInterface::class);
        $urlFinder->expects(self::exactly(3))->method('findOneByData')->willReturn(null);
        $cache = $this->createMock(CacheInterface::class);
        $cache->method('load')->willReturn(false);
        $cache->expects(self::never())->method('save');

        $controller = new MdMirror(
            $this->response(),
            $request,
            $storeManager,
            $this->createMock(RawFactory::class),
            $this->config(),
            $urlFinder,
            $this->createMock(ProductRepositoryInterface::class),
            $this->createMock(CategoryRepositoryInterface::class),
            $this->createMock(PageRepositoryInterface::class),
            $this->createMock(SanitizerInterface::class),
            $this->createMock(OutputContextFactory::class),
            $cache,
            $this->createMock(LoggerInterface::class),
            $this->createMock(RestrictedCatalogGuard::class)
        );

        $controller->execute();
    }
}
