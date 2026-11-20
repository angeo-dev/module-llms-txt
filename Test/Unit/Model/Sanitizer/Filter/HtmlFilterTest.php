<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Model\Sanitizer\Filter;

use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Model\Sanitizer\Filter\HtmlFilter;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Angeo\LlmsTxt\Model\Sanitizer\Filter\HtmlFilter
 */
class HtmlFilterTest extends TestCase
{
    private HtmlFilter $filter;
    private OutputContextInterface $context;

    protected function setUp(): void
    {
        $this->filter  = new HtmlFilter();
        $this->context = $this->createMock(OutputContextInterface::class);
    }

    public function testStripsTagsAndDecodesEntities(): void
    {
        $html = '<p>Hello <strong>&amp; goodbye</strong></p>';
        $out = $this->filter->filter($html, $this->context);
        self::assertSame('Hello & goodbye', $out);
    }

    public function testRemovesScriptAndStyleContents(): void
    {
        $html = '<p>Visible.</p><script>alert(1)</script><style>.x{}</style>';
        $out = $this->filter->filter($html, $this->context);
        self::assertSame('Visible.', $out);
        self::assertStringNotContainsString('alert', $out);
        self::assertStringNotContainsString('.x{}', $out);
    }

    public function testParagraphBreaksConvertToNewlines(): void
    {
        $html = '<p>Para one.</p><p>Para two.</p>';
        $out = $this->filter->filter($html, $this->context);
        self::assertStringContainsString("Para one.\n", $out);
        self::assertStringContainsString('Para two.', $out);
    }

    public function testCommentsRemoved(): void
    {
        $html = 'Text <!-- comment --> more text';
        $out = $this->filter->filter($html, $this->context);
        self::assertSame('Text more text', $out);
    }

    public function testNonBreakingSpaceNormalized(): void
    {
        $html = "Hello\xc2\xa0world";
        $out = $this->filter->filter($html, $this->context);
        self::assertSame('Hello world', $out);
    }

    public function testEmptyInputReturnsEmpty(): void
    {
        self::assertSame('', $this->filter->filter('', $this->context));
    }

    /**
     * SECURITY (3.1.0): an unterminated <script> block must be removed to the
     * end of input — inline JS sometimes carries analytics tokens or API keys
     * that must never leak into the public files.
     */
    public function testUnterminatedScriptBlockIsFullyRemoved(): void
    {
        $html = '<p>Safe.</p><script>var apiKey="SECRET123";';
        $out = $this->filter->filter($html, $this->context);
        self::assertSame('Safe.', $out);
        self::assertStringNotContainsString('SECRET123', $out);
    }

    /**
     * SECURITY (3.1.0): entity-encoded markup must not be resurrected into
     * live tags by the entity-decoding step (stored-XSS defense for consumers
     * that render the .md mirror as HTML).
     */
    public function testEntityEncodedScriptDoesNotBecomeLiveMarkup(): void
    {
        $html = 'Before &lt;script&gt;alert(1)&lt;/script&gt; after';
        $out = $this->filter->filter($html, $this->context);
        self::assertStringNotContainsString('<script', $out);
        self::assertStringNotContainsString('alert(1)', $out);
    }

    public function testLegitimateAngleBracketTextSurvives(): void
    {
        $out = $this->filter->filter('Price is 5 < 10 EUR', $this->context);
        self::assertSame('Price is 5 < 10 EUR', $out);
    }

    public function testEncodedLessThanInPlainTextSurvives(): void
    {
        // 5.0.0: the second strip_tags() pass used to delete "<3 year olds".
        $filter = new \Angeo\LlmsTxt\Model\Sanitizer\Filter\HtmlFilter();
        $ctx = $this->createMock(\Angeo\LlmsTxt\Api\OutputContextInterface::class);

        self::assertSame('Fits <3 year olds', $filter->filter('<p>Fits &lt;3 year olds</p>', $ctx));
        self::assertSame('', $filter->filter('&lt;img src=x onerror=alert(1)&gt;', $ctx));
        self::assertSame('alert(1)', $filter->filter('&lt;script&gt;x&lt;/script&gt;alert(1)', $ctx));
    }
}
