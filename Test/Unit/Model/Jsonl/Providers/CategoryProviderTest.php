<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Model\Jsonl\Providers;

use Angeo\LlmsTxt\Model\Jsonl\Providers\CategoryProvider;
use Magento\Catalog\Model\Category;
use Magento\Catalog\Model\ResourceModel\Category\Collection;
use Magento\Catalog\Model\ResourceModel\Category\CollectionFactory;
use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class CategoryProviderTest extends TestCase
{
    private CollectionFactory|MockObject $collectionFactory;
    private Collection|MockObject $collection;
    private StoreInterface|MockObject $store;
    private CategoryProvider $provider;

    protected function setUp(): void
    {
        $this->collectionFactory = $this->createMock(CollectionFactory::class);
        $this->collection = $this->createMock(Collection::class);
        $this->store = $this->createMock(StoreInterface::class);

        $this->collectionFactory->method('create')->willReturn($this->collection);
        $this->collection->method('setStoreId')->willReturnSelf();
        $this->collection->method('addAttributeToSelect')->willReturnSelf();
        $this->collection->method('addAttributeToFilter')->willReturnSelf();
        $this->collection->method('setPageSize')->willReturnSelf();

        $this->store->method('getId')->willReturn(1);
        $this->store->method('getCode')->willReturn('default');
        $this->store->method('getRootCategoryId')->willReturn(2);

        $this->provider = new CategoryProvider($this->collectionFactory);
    }

    /**
     * THE critical regression test: v1 only returned the last category.
     */
    public function testAllCategoriesAreEncoded(): void
    {
        $cat1 = $this->mockCategory(10, 'Electronics', 3);
        $cat2 = $this->mockCategory(11, 'Clothing', 3);
        $cat3 = $this->mockCategory(12, 'Tools', 3);

        $this->collection->method('getIterator')
            ->willReturn(new \ArrayIterator([$cat1, $cat2, $cat3]));

        $output = $this->provider->provide($this->store);
        $lines = array_filter(explode("\n", trim($output)));

        $this->assertCount(3, $lines, 'All 3 categories must be present — not just the last one');

        foreach ($lines as $line) {
            $decoded = json_decode($line, true);
            $this->assertNotNull($decoded, "Each line must be valid JSON: $line");
            $this->assertSame('category', $decoded['type']);
        }

        $names = array_map(fn($l) => json_decode($l, true)['name'], $lines);
        $this->assertContains('Electronics', $names);
        $this->assertContains('Clothing', $names);
        $this->assertContains('Tools', $names);
    }

    public function testEmptyCollectionReturnsEmptyString(): void
    {
        $this->collection->method('getIterator')->willReturn(new \ArrayIterator([]));

        $output = $this->provider->provide($this->store);

        $this->assertSame('', $output);
    }

    public function testOutputIsValidJsonlFormat(): void
    {
        $cat = $this->mockCategory(5, 'Books', 2);
        $this->collection->method('getIterator')->willReturn(new \ArrayIterator([$cat]));

        $output = $this->provider->provide($this->store);
        $line = trim($output);

        $decoded = json_decode($line, true);
        $this->assertIsArray($decoded);
        $this->assertArrayHasKey('type', $decoded);
        $this->assertArrayHasKey('store', $decoded);
        $this->assertArrayHasKey('id', $decoded);
        $this->assertArrayHasKey('name', $decoded);
        $this->assertArrayHasKey('embedding_text', $decoded);
    }

    public function testCategoryWithEmptyNameIsSkipped(): void
    {
        $emptyNameCat = $this->mockCategory(99, '', 3);
        $validCat = $this->mockCategory(10, 'Valid Category', 3);

        $this->collection->method('getIterator')
            ->willReturn(new \ArrayIterator([$emptyNameCat, $validCat]));

        $output = $this->provider->provide($this->store);
        $lines = array_filter(explode("\n", trim($output)));

        $this->assertCount(1, $lines);
        $this->assertStringContainsString('Valid Category', $lines[array_key_first($lines)]);
    }

    private function mockCategory(int $id, string $name, int $parentId): Category|MockObject
    {
        $cat = $this->createMock(Category::class);
        $cat->method('getId')->willReturn($id);
        $cat->method('getName')->willReturn($name);
        $cat->method('getParentId')->willReturn($parentId);
        $cat->method('getUrl')->willReturn("https://example.com/cat-{$id}");
        $cat->method('getDescription')->willReturn("Description of {$name}");
        return $cat;
    }
}
