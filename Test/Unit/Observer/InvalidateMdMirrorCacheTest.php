<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Observer;

use Angeo\LlmsTxt\Model\Cache\MdMirrorCacheKey;
use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\Pipeline\ChangeMarker;
use Angeo\LlmsTxt\Observer\InvalidateMdMirrorCache;
use Magento\Cms\Model\Page;
use Magento\Framework\App\CacheInterface;
use Magento\Framework\Event;
use Magento\Framework\Event\Observer;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use Magento\UrlRewrite\Model\UrlFinderInterface;
use Magento\UrlRewrite\Service\V1\Data\UrlRewrite;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class InvalidateMdMirrorCacheTest extends TestCase
{
    private function observer(string $eventName, string $dataKey, object $entity): Observer
    {
        $event = new Event([$dataKey => $entity]);
        $event->setName($eventName);

        return new Observer(['event' => $event]);
    }

    /**
     * 5.0.0: CMS pages arrive under "object"/"data_object"; the old "page" key
     * never matched, so CMS mirrors were never invalidated.
     */
    public function testCmsPageSaveInvalidatesEveryPathForm(): void
    {
        $page = $this->createMock(Page::class);
        $page->method('getId')->willReturn(5);

        $store = $this->createMock(Store::class);
        $store->method('getId')->willReturn(1);
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn([$store]);

        $config = $this->createMock(Config::class);
        $config->method('isMdMirrorEnabled')->willReturn(true);
        $config->method('isInvalidateMirrorOnSaveEnabled')->willReturn(true);

        $rewrite = $this->createMock(UrlRewrite::class);
        $rewrite->method('getRequestPath')->willReturn('about.html');
        $urlFinder = $this->createMock(UrlFinderInterface::class);
        $urlFinder->expects(self::once())->method('findAllByData')
            ->with(self::callback(static fn (array $d) => $d['entity_type'] === 'cms-page' && $d['entity_id'] === 5))
            ->willReturn([$rewrite]);

        $keys  = new MdMirrorCacheKey();
        $cache = $this->createMock(CacheInterface::class);
        $removed = [];
        $cache->method('remove')->willReturnCallback(static function (string $k) use (&$removed) {
            $removed[] = $k;
            return true;
        });

        $marker = $this->createMock(ChangeMarker::class);
        $marker->expects(self::never())->method('markDeleted');

        (new InvalidateMdMirrorCache(
            $cache,
            $urlFinder,
            $storeManager,
            $keys,
            $config,
            $this->createMock(LoggerInterface::class),
            $marker
        ))->execute($this->observer('cms_page_save_after', 'object', $page));

        self::assertSame([$keys->forPath(1, 'about.html'), $keys->forPath(1, 'about')], $removed);
    }

    public function testDeleteRecordsChangeMarker(): void
    {
        $page = $this->createMock(Page::class);
        $page->method('getId')->willReturn(5);

        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn([]);

        $marker = $this->createMock(ChangeMarker::class);
        $marker->expects(self::once())->method('markDeleted');

        (new InvalidateMdMirrorCache(
            $this->createMock(CacheInterface::class),
            $this->createMock(UrlFinderInterface::class),
            $storeManager,
            new MdMirrorCacheKey(),
            $this->createMock(Config::class),
            $this->createMock(LoggerInterface::class),
            $marker
        ))->execute($this->observer('cms_page_delete_after', 'data_object', $page));
    }
}
