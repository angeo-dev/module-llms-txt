<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Model\Url;

use Angeo\LlmsTxt\Model\Url\Origin;
use PHPUnit\Framework\TestCase;

/**
 * @covers \Angeo\LlmsTxt\Model\Url\Origin
 */
class OriginTest extends TestCase
{
    public function testStripsPathStoreCodeAndIndexPhp(): void
    {
        self::assertSame('https://shop.test', Origin::fromUrl('https://shop.test/'));
        self::assertSame('https://shop.test', Origin::fromUrl('https://shop.test/en/'));
        self::assertSame('https://shop.test', Origin::fromUrl('https://shop.test/index.php/en'));
    }

    public function testKeepsPort(): void
    {
        self::assertSame('http://localhost:8080', Origin::fromUrl('http://localhost:8080/de/'));
    }

    public function testNonAbsoluteUrlIsReturnedTrimmed(): void
    {
        self::assertSame('', Origin::fromUrl(''));
        self::assertSame('/en', Origin::fromUrl('/en/'));
    }
}
