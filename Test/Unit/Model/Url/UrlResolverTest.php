<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Model\Url;

use Angeo\LlmsTxt\Api\UrlResolverInterface;
use Angeo\LlmsTxt\Model\Url\UrlResolver;
use Magento\Framework\App\ResourceConnection;
use Magento\Framework\DB\Adapter\AdapterInterface;
use Magento\Framework\DB\Select;
use Magento\Framework\UrlInterface;
use Magento\Store\Api\StoreRepositoryInterface;
use Magento\Store\Model\Store;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Angeo\LlmsTxt\Model\Url\UrlResolver
 */
class UrlResolverTest extends TestCase
{
    /**
     * UrlResolver streams rows with $connection->query($select)->fetch().
     * AdapterInterface::query() has no native return type, so a light stub
     * that walks the given rows and then returns false is enough here.
     *
     * @param array<int, array<string, mixed>> $rows
     */
    private function statementStub(array $rows): object
    {
        return new class ($rows) {
            /** @var array<int, array<string, mixed>> */
            private array $rows;

            /**
             * @param array<int, array<string, mixed>> $rows
             */
            public function __construct(array $rows)
            {
                $this->rows = $rows;
            }

            /**
             * @return array<string, mixed>|false
             */
            public function fetch()
            {
                $row = array_shift($this->rows);

                return $row ?? false;
            }
        };
    }

    public function testBatchWarmupAndLookups(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('order')->willReturnSelf();

        $statement = $this->statementStub([
            ['entity_id' => 100, 'entity_type' => 'product',  'request_path' => 'p1.html'],
            ['entity_id' => 100, 'entity_type' => 'product',  'request_path' => 'duplicate.html'],
            ['entity_id' => 200, 'entity_type' => 'category', 'request_path' => 'shoes.html'],
            ['entity_id' => 300, 'entity_type' => 'cms-page', 'request_path' => 'about'],
        ]);

        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('select')->willReturn($select);
        $adapter->method('query')->willReturn($statement);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($adapter);
        $resource->method('getTableName')->willReturn('url_rewrite');

        $store = $this->createMock(Store::class);
        $store->method('isFrontUrlSecure')->willReturn(true);
        $store->method('getBaseUrl')->willReturnCallback(
            static fn (string $type = UrlInterface::URL_TYPE_LINK, ?bool $secure = null): string =>
                $type === UrlInterface::URL_TYPE_LINK && $secure === true
                    ? 'https://shop.test/en/'
                    : 'http://shop.test/'
        );
        $repo = $this->createMock(StoreRepositoryInterface::class);
        $repo->method('getById')->willReturn($store);

        $resolver = new UrlResolver($resource, $repo);

        self::assertSame(
            'https://shop.test/en/p1.html',
            $resolver->resolve(UrlResolverInterface::ENTITY_PRODUCT, 100, 1)
        );
        self::assertSame(
            'https://shop.test/en/shoes.html',
            $resolver->resolve(UrlResolverInterface::ENTITY_CATEGORY, 200, 1)
        );
        self::assertSame(
            'https://shop.test/en/about',
            $resolver->resolve(UrlResolverInterface::ENTITY_CMS_PAGE, 300, 1)
        );

        // Unknown entity → null
        self::assertNull(
            $resolver->resolve(UrlResolverInterface::ENTITY_PRODUCT, 999, 1)
        );
    }

    public function testFirstRewriteWinsOnDuplicates(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('order')->willReturnSelf();

        $statement = $this->statementStub([
            ['entity_id' => 100, 'entity_type' => 'product', 'request_path' => 'first.html'],
            ['entity_id' => 100, 'entity_type' => 'product', 'request_path' => 'second.html'],
        ]);

        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('select')->willReturn($select);
        $adapter->method('query')->willReturn($statement);

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($adapter);
        $resource->method('getTableName')->willReturn('url_rewrite');

        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn('https://shop.test/');
        $repo = $this->createMock(StoreRepositoryInterface::class);
        $repo->method('getById')->willReturn($store);

        $resolver = new UrlResolver($resource, $repo);

        self::assertSame(
            'https://shop.test/first.html',
            $resolver->resolve(UrlResolverInterface::ENTITY_PRODUCT, 100, 1)
        );
    }

    public function testWarmUpIsIdempotent(): void
    {
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnSelf();
        $select->method('order')->willReturnSelf();

        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('select')->willReturn($select);
        $adapter->expects(self::once())
            ->method('query')
            ->willReturn($this->statementStub([]));

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($adapter);
        $resource->method('getTableName')->willReturn('url_rewrite');

        $store = $this->createMock(Store::class);
        $store->method('getBaseUrl')->willReturn('https://shop.test/');
        $repo = $this->createMock(StoreRepositoryInterface::class);
        $repo->method('getById')->willReturn($store);

        $resolver = new UrlResolver($resource, $repo);
        $resolver->warmUp(1);
        $resolver->warmUp(1); // second call hits the cache, no second DB call
    }

    public function testOnlyCanonicalRewritesAreSelectedInStableOrder(): void
    {
        // 5.0.0: category-path product URLs (metadata set) must never win.
        $where = [];
        $select = $this->createMock(Select::class);
        $select->method('from')->willReturnSelf();
        $select->method('where')->willReturnCallback(function (string $cond) use (&$where, &$select) {
            $where[] = $cond;
            return $select;
        });
        $select->expects(self::once())->method('order')->with('url_rewrite_id ASC')->willReturnSelf();

        $adapter = $this->createMock(AdapterInterface::class);
        $adapter->method('select')->willReturn($select);
        $adapter->method('query')->willReturn($this->statementStub([]));

        $resource = $this->createMock(ResourceConnection::class);
        $resource->method('getConnection')->willReturn($adapter);
        $resource->method('getTableName')->willReturn('url_rewrite');

        $repo = $this->createMock(StoreRepositoryInterface::class);
        $repo->method('getById')->willReturn($this->createMock(Store::class));

        (new UrlResolver($resource, $repo))->warmUp(1);

        self::assertContains('metadata IS NULL', $where);
    }
}
