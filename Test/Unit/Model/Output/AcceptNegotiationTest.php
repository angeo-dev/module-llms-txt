<?php
declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Model\Output;

use Angeo\LlmsTxt\Model\Output\AcceptNegotiation;
use PHPUnit\Framework\TestCase;

/**
 * 5.2.0: which Accept headers get markdown.
 */
class AcceptNegotiationTest extends TestCase
{
    /**
     * @dataProvider headers
     */
    public function testPrefersMarkdown(string $accept, bool $expected): void
    {
        self::assertSame($expected, AcceptNegotiation::prefersMarkdown($accept));
    }

    /** @return array<string, array{string, bool}> */
    public static function headers(): array
    {
        return [
            'isitagentready'   => ['text/markdown', true],
            'agent with html'  => ['text/markdown, text/html;q=0.8', true],
            'agent fallback'   => ['text/markdown, */*;q=0.1', true],
            'browser'          => ['text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8', false],
            'tie goes to html' => ['text/html, text/markdown', false],
            'html preferred'   => ['text/html, text/markdown;q=0.5', false],
            'markdown refused' => ['text/markdown;q=0', false],
            'anything'         => ['*/*', false],
            'empty'            => ['', false],
            'x-markdown'       => ['text/x-markdown', true],
        ];
    }
}
