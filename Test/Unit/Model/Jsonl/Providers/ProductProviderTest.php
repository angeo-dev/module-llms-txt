<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Model\Jsonl\Providers;

use Angeo\LlmsTxt\Model\Jsonl\Providers\ProductProvider;
use Magento\Catalog\Model\Product;
use Magento\Catalog\Model\ResourceModel\Product\Collection;
use Magento\Catalog\Model\ResourceModel\Product\CollectionFactory;
use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class ProductProviderTest extends TestCase
{
    private CollectionFactory|MockObject $collectionFactory;
    private StoreInterface|MockObject $store;

    protected function setUp(): void
    {
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->store = $this->createMock(StoreInterface::class);
        $this->store->method('getId')->willReturn(1);
        $this->store->method('getCode')->willReturn('default');
        $this->store->method('getCurrentCurrencyCode')->willReturn('USD');
    }

    private function mockCollection(array $products, int $lastPage = 1): Collection|MockObject
    {
        $col = $this->createMock(Collection::class);
        $col->method('setStoreId')->willReturnSelf();
        $col->method('addStoreFilter')->willReturnSelf();
        $col->method('addAttributeToSelect')->willReturnSelf();
        $col->method('addAttributeToFilter')->willReturnSelf();
        $col->method('addUrlRewrite')->willReturnSelf();
        $col->method('setPageSize')->willReturnSelf();
        $col->method('setCurPage')->willReturnSelf();
        $col->method('getLastPageNumber')->willReturn($lastPage);
        $col->method('clear')->willReturnSelf();
        $col->method('getIterator')->willReturn(new \ArrayIterator($products));
        return $col;
    }

    private function mockProduct(int $id, string $name, float $price = 9.99): Product|MockObject
    {
        $p = $this->createMock(Product::class);
        $p->method('getId')->willReturn($id);
        $p->method('getSku')->willReturn("SKU-{$id}");
        $p->method('getName')->willReturn($name);
        $p->method('getPrice')->willReturn($price);
        $p->method('getShortDescription')->willReturn('Short desc');
        $p->method('getDescription')->willReturn('Full description');
        $p->method('getProductUrl')->willReturn("https://example.com/product-{$id}");
        return $p;
    }

    public function testAllProductsAreEncoded(): void
    {
        $products = [$this->mockProduct(1, 'Widget A'), $this->mockProduct(2, 'Widget B'), $this->mockProduct(3, 'Widget C')];
        $col = $this->mockCollection($products);
        $this->collectionFactory->method('create')->willReturn($col);

        $output = (new ProductProvider($this->collectionFactory))->provide($this->store);
        $lines = array_filter(explode("\n", trim($output)));

        $this->assertCount(3, $lines, 'All 3 products must be present');

        $names = array_map(fn($l) => json_decode($l, true)['title'] ?? '', $lines);
        $this->assertContains('Widget A', $names);
        $this->assertContains('Widget B', $names);
        $this->assertContains('Widget C', $names);
    }

    public function testEmptyCollectionReturnsEmptyString(): void
    {
        $col = $this->mockCollection([]);
        $this->collectionFactory->method('create')->willReturn($col);

        $output = (new ProductProvider($this->collectionFactory))->provide($this->store);

        $this->assertSame('', $output);
    }

    public function testOutputIsValidJsonl(): void
    {
        $col = $this->mockCollection([$this->mockProduct(1, 'Test')]);
        $this->collectionFactory->method('create')->willReturn($col);

        $output = (new ProductProvider($this->collectionFactory))->provide($this->store);
        $decoded = json_decode(trim($output), true);

        $this->assertIsArray($decoded);
        $this->assertSame('product', $decoded['type']);
        $this->assertArrayHasKey('sku', $decoded);
        $this->assertArrayHasKey('price', $decoded);
        $this->assertArrayHasKey('currency', $decoded);
        $this->assertArrayHasKey('embedding_text', $decoded);
    }

    public function testPaginationIteratesAllPages(): void
    {
        $page1Products = [$this->mockProduct(1, 'P1'), $this->mockProduct(2, 'P2')];
        $page2Products = [$this->mockProduct(3, 'P3')];

        $col1 = $this->mockCollection($page1Products, 2);
        $col2 = $this->mockCollection($page2Products, 2);

        $callCount = 0;
        $this->collectionFactory->method('create')->willReturnCallback(function () use ($col1, $col2, &$callCount) {
            return $callCount++ === 0 ? $col1 : $col2;
        });

        $output = (new ProductProvider($this->collectionFactory))->provide($this->store);
        $lines = array_filter(explode("\n", trim($output)));

        $this->assertCount(3, $lines, 'Both pages must be iterated');
    }
}
