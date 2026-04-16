<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Model\Llms\Providers;

use Angeo\LlmsTxt\Model\Llms\Providers\ProductProvider;
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
        $this->store->method('getCurrentCurrencyCode')->willReturn('EUR');
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

    private function mockProduct(string $name, float $price, string $url): Product|MockObject
    {
        $p = $this->createMock(Product::class);
        $p->method('getName')->willReturn($name);
        $p->method('getPrice')->willReturn($price);
        $p->method('getProductUrl')->willReturn($url);
        $p->method('getShortDescription')->willReturn('Short desc for ' . $name);
        $p->method('getId')->willReturn(rand(1, 9999));
        return $p;
    }

    public function testOutputStartsWithH2Section(): void
    {
        $col = $this->mockCollection([$this->mockProduct('Widget', 9.99, 'https://ex.com/widget')]);
        $this->collectionFactory->method('create')->willReturn($col);

        $output = (new ProductProvider($this->collectionFactory))->provide($this->store);

        $this->assertStringStartsWith('## Products', $output);
    }

    public function testProductFormattedAsMarkdownLink(): void
    {
        $col = $this->mockCollection([
            $this->mockProduct('Blue Widget', 29.99, 'https://example.com/blue-widget'),
        ]);
        $this->collectionFactory->method('create')->willReturn($col);

        $output = (new ProductProvider($this->collectionFactory))->provide($this->store);

        $this->assertStringContainsString('[Blue Widget](https://example.com/blue-widget)', $output);
    }

    public function testPriceIncludedInOutput(): void
    {
        $col = $this->mockCollection([
            $this->mockProduct('Widget', 19.99, 'https://example.com/w'),
        ]);
        $this->collectionFactory->method('create')->willReturn($col);

        $output = (new ProductProvider($this->collectionFactory))->provide($this->store);

        $this->assertStringContainsString('19.99', $output);
        $this->assertStringContainsString('EUR', $output);
    }

    public function testEmptyCollectionReturnsEmptyString(): void
    {
        $col = $this->mockCollection([]);
        $this->collectionFactory->method('create')->willReturn($col);

        $output = (new ProductProvider($this->collectionFactory))->provide($this->store);

        $this->assertSame('', $output);
    }

    public function testEachProductOnSeparateLine(): void
    {
        $products = [
            $this->mockProduct('P1', 1.0, 'https://ex.com/p1'),
            $this->mockProduct('P2', 2.0, 'https://ex.com/p2'),
            $this->mockProduct('P3', 3.0, 'https://ex.com/p3'),
        ];
        $col = $this->mockCollection($products);
        $this->collectionFactory->method('create')->willReturn($col);

        $output = (new ProductProvider($this->collectionFactory))->provide($this->store);
        $linkLines = array_filter(explode("\n", $output), fn($l) => str_starts_with($l, '- ['));

        $this->assertCount(3, $linkLines);
    }
}
