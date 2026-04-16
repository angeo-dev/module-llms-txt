<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Model\Llms\Providers;

use Angeo\LlmsTxt\Model\Llms\Providers\StoreProvider;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Store\Api\Data\StoreInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class StoreProviderTest extends TestCase
{
    private ScopeConfigInterface|MockObject $scopeConfig;
    private StoreInterface|MockObject $store;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->store = $this->createMock(StoreInterface::class);

        $this->store->method('getName')->willReturn('My Store');
        $this->store->method('getBaseUrl')->willReturn('https://example.com/');
        $this->store->method('getCurrentCurrencyCode')->willReturn('USD');
        $this->store->method('getLocaleCode')->willReturn('en_US');
        $this->store->method('getId')->willReturn(1);
    }

    public function testOutputStartsWithH1Title(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('');

        $output = (new StoreProvider($this->scopeConfig))->provide($this->store);

        $this->assertStringStartsWith('# My Store', $output);
    }

    public function testOutputContainsStoreUrl(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('');

        $output = (new StoreProvider($this->scopeConfig))->provide($this->store);

        $this->assertStringContainsString('https://example.com', $output);
    }

    public function testDescriptionIncludedAsBlockquote(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('We sell great products worldwide.');

        $output = (new StoreProvider($this->scopeConfig))->provide($this->store);

        $this->assertStringContainsString('> We sell great products worldwide.', $output);
    }

    public function testNoDescriptionSkipsBlockquote(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('');

        $output = (new StoreProvider($this->scopeConfig))->provide($this->store);

        // Metadata blockquotes (URL, Currency) should still be present
        $this->assertStringContainsString('> Store URL:', $output);
        // But no "> We sell" description line
        $lines = explode("\n", $output);
        $descLines = array_filter($lines, fn($l) => str_starts_with($l, '> ') && !str_starts_with($l, '> Store') && !str_starts_with($l, '> Currency') && !str_starts_with($l, '> Locale'));
        $this->assertEmpty($descLines);
    }

    public function testCurrencyAndLocaleIncluded(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('');

        $output = (new StoreProvider($this->scopeConfig))->provide($this->store);

        $this->assertStringContainsString('USD', $output);
        $this->assertStringContainsString('en_US', $output);
    }

    public function testOutputEndsWithDoubleNewline(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('');

        $output = (new StoreProvider($this->scopeConfig))->provide($this->store);

        $this->assertStringEndsWith("\n\n", $output);
    }
}
