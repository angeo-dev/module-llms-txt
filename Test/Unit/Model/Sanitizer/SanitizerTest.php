<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Model\Sanitizer;

use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Api\SanitizerFilterInterface;
use Angeo\LlmsTxt\Model\Sanitizer\Sanitizer;
use Magento\Store\Model\Store;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

/**
 * @covers \Angeo\LlmsTxt\Model\Sanitizer\Sanitizer
 */
class SanitizerTest extends TestCase
{
    private LoggerInterface&MockObject $logger;
    private OutputContextInterface&MockObject $context;

    protected function setUp(): void
    {
        $this->logger  = $this->createMock(LoggerInterface::class);
        $this->context = $this->createMock(OutputContextInterface::class);
    }

    public function testEmptyInputReturnsEmpty(): void
    {
        $sanitizer = new Sanitizer($this->logger, []);
        self::assertSame('', $sanitizer->sanitize('', $this->context));
    }

    public function testFiltersAppliedInOrder(): void
    {
        $f1 = $this->createMock(SanitizerFilterInterface::class);
        $f1->method('filter')->willReturnCallback(fn(string $c) => $c . 'A');
        $f2 = $this->createMock(SanitizerFilterInterface::class);
        $f2->method('filter')->willReturnCallback(fn(string $c) => $c . 'B');

        $sanitizer = new Sanitizer($this->logger, [$f1, $f2]);
        self::assertSame('XAB', $sanitizer->sanitize('X', $this->context));
    }

    public function testFailingFilterIsLoggedAndPipelineContinues(): void
    {
        $f1 = $this->createMock(SanitizerFilterInterface::class);
        $f1->method('filter')->willThrowException(new \RuntimeException('boom'));
        $f2 = $this->createMock(SanitizerFilterInterface::class);
        $f2->method('filter')->willReturnCallback(fn(string $c) => strtoupper($c));

        $store = $this->createMock(Store::class);
        $store->method('getCode')->willReturn('default');
        $this->context->method('getStore')->willReturn($store);

        $this->logger->expects(self::once())->method('warning');

        $sanitizer = new Sanitizer($this->logger, [$f1, $f2]);
        self::assertSame('HELLO', $sanitizer->sanitize('hello', $this->context));
    }

    public function testTruncationOnWordBoundary(): void
    {
        $sanitizer = new Sanitizer($this->logger, []);
        $input = 'one two three four five six seven eight nine ten';
        $out = $sanitizer->sanitize($input, $this->context, 20);

        // Must be ≤ 20 + 1 (ellipsis) and not split a word.
        self::assertLessThanOrEqual(21, mb_strlen($out));
        self::assertStringEndsWith('…', $out);
        self::assertStringNotContainsString(' ten', $out);
        // Boundary preserved: the last char before ellipsis is a non-space letter.
        self::assertMatchesRegularExpression('/[a-z]…$/u', $out);
    }

    public function testNoTruncationWhenWithinLimit(): void
    {
        $sanitizer = new Sanitizer($this->logger, []);
        self::assertSame('short', $sanitizer->sanitize('short', $this->context, 100));
    }
}
