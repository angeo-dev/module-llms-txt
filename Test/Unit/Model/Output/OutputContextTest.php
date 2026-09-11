<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Model\Output;

use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Model\Output\OutputContext;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\UrlInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\ScopeInterface;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Angeo\LlmsTxt\Model\Output\OutputContext
 */
class OutputContextTest extends TestCase
{
    private Store $store;
    private ScopeConfigInterface $scopeConfig;

    protected function setUp(): void
    {
        $this->store = $this->createMock(Store::class);
        $this->store->method('getId')->willReturn(1);
        $this->store->method('getBaseUrl')->with(UrlInterface::URL_TYPE_WEB)->willReturn('https://example.test/');
        $this->store->method('getCurrentCurrencyCode')->willReturn('USD');

        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
    }

    public function testGettersReturnConstructedValues(): void
    {
        $ctx = new OutputContext(
            $this->store,
            OutputContextInterface::FORMAT_JSONL,
            OutputContextInterface::VERBOSITY_DATASET,
            42,
            $this->scopeConfig
        );

        self::assertSame($this->store, $ctx->getStore());
        self::assertSame(OutputContextInterface::FORMAT_JSONL, $ctx->getFormat());
        self::assertSame(OutputContextInterface::VERBOSITY_DATASET, $ctx->getVerbosity());
        self::assertSame(42, $ctx->getCustomerGroupId());
    }

    public function testLocaleNormalizedToBcp47(): void
    {
        $this->scopeConfig->method('getValue')
            ->with('general/locale/code', ScopeInterface::SCOPE_STORE, 1)
            ->willReturn('en_US');

        $ctx = new OutputContext(
            $this->store,
            OutputContextInterface::FORMAT_LLMS_TXT,
            OutputContextInterface::VERBOSITY_COMPACT,
            0,
            $this->scopeConfig
        );

        self::assertSame('en-US', $ctx->getLocaleCode());
    }

    public function testBaseUrlIsTrimmed(): void
    {
        $ctx = new OutputContext(
            $this->store,
            OutputContextInterface::FORMAT_LLMS_TXT,
            OutputContextInterface::VERBOSITY_COMPACT,
            0,
            $this->scopeConfig
        );

        self::assertSame('https://example.test', $ctx->getBaseUrl());
    }

    public function testSharedBag(): void
    {
        $ctx = new OutputContext(
            $this->store,
            OutputContextInterface::FORMAT_LLMS_TXT,
            OutputContextInterface::VERBOSITY_COMPACT,
            0,
            $this->scopeConfig
        );

        self::assertNull($ctx->getShared('missing'));
        $ctx->setShared('count', 42);
        self::assertSame(42, $ctx->getShared('count'));
    }
}
