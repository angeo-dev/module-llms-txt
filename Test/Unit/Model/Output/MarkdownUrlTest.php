<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Model\Output;

use Angeo\LlmsTxt\Model\Output\MarkdownUrl;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Angeo\LlmsTxt\Model\Output\MarkdownUrl
 */
class MarkdownUrlTest extends TestCase
{
    private MarkdownUrl $subject;

    protected function setUp(): void
    {
        $this->subject = new MarkdownUrl();
    }

    /**
     * @dataProvider urls
     */
    public function testForUrl(?string $input, ?string $expected): void
    {
        self::assertSame($expected, $this->subject->forUrl($input));
    }

    /**
     * @return array<string, array{0: ?string, 1: ?string}>
     */
    public static function urls(): array
    {
        return [
            'html suffix is kept and .md appended' => [
                'https://shop.test/blue-shirt.html',
                'https://shop.test/blue-shirt.html.md',
            ],
            'suffixless url' => [
                'https://shop.test/gear/bags',
                'https://shop.test/gear/bags.md',
            ],
            'trailing slash is dropped first' => [
                'https://shop.test/gear/bags/',
                'https://shop.test/gear/bags.md',
            ],
            'store root has no entity to mirror' => [
                'https://shop.test/',
                'https://shop.test/',
            ],
            'already a mirror' => [
                'https://shop.test/blue-shirt.html.md',
                'https://shop.test/blue-shirt.html.md',
            ],
            'non-default port survives' => [
                'https://shop.test:8443/blue-shirt.html',
                'https://shop.test:8443/blue-shirt.html.md',
            ],
            'store-code path is part of the path' => [
                'https://shop.test/de/blue-shirt.html',
                'https://shop.test/de/blue-shirt.html.md',
            ],
            'null passes through' => [null, null],
            'empty passes through' => ['', ''],
        ];
    }
}
