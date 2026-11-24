<?php
declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Observer;

use Angeo\LlmsTxt\Observer\DiscoveryLinkHeader;
use PHPUnit\Framework\TestCase;

/**
 * 5.2.0: Link header merge rules.
 */
class DiscoveryLinkHeaderTest extends TestCase
{
    private const LLMS = '<https://s.example/llms.txt>; rel="describedby"; type="text/plain"';
    private const CAT = '<https://s.example/.well-known/ai-catalog.json>; rel="ai-catalog"';

    public function testAddsLinks(): void
    {
        self::assertSame(self::LLMS . ', ' . self::CAT, DiscoveryLinkHeader::merge('', [self::LLMS, self::CAT]));
    }

    public function testKeepsExistingAndDoesNotRepeat(): void
    {
        $existing = '</static/x.css>; rel=preload; as=style, ' . self::LLMS;
        self::assertSame($existing . ', ' . self::CAT, DiscoveryLinkHeader::merge($existing, [self::LLMS, self::CAT]));
    }

    public function testOrigin(): void
    {
        self::assertSame('https://s.example', DiscoveryLinkHeader::originOf('https://s.example/de'));
        self::assertSame('http://s.example:8080', DiscoveryLinkHeader::originOf('http://s.example:8080/'));
    }
}
