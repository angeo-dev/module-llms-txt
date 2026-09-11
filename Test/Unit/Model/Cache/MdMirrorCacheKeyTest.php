<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Model\Cache;

use Angeo\LlmsTxt\Model\Cache\MdMirrorCacheKey;
use PHPUnit\Framework\TestCase;

/**
 * The controller and the save observer must derive the SAME key from the same
 * request path, otherwise invalidation silently misses and edited pages keep
 * serving stale markdown. These tests pin that contract.
 *
 * @covers \Angeo\LlmsTxt\Model\Cache\MdMirrorCacheKey
 */
class MdMirrorCacheKeyTest extends TestCase
{
    private MdMirrorCacheKey $subject;

    protected function setUp(): void
    {
        $this->subject = new MdMirrorCacheKey();
    }

    public function testLeadingSlashIsIrrelevant(): void
    {
        // The controller strips it; url_rewrite rows do not carry it. Both
        // callers must land on the same key regardless.
        self::assertSame(
            $this->subject->forPath(1, 'blue-shirt.html'),
            $this->subject->forPath(1, '/blue-shirt.html')
        );
    }

    public function testKeysAreScopedPerStore(): void
    {
        self::assertNotSame(
            $this->subject->forPath(1, 'blue-shirt.html'),
            $this->subject->forPath(2, 'blue-shirt.html')
        );
    }

    public function testDifferentPathsGiveDifferentKeys(): void
    {
        self::assertNotSame(
            $this->subject->forPath(1, 'blue-shirt.html'),
            $this->subject->forPath(1, 'red-shirt.html')
        );
    }

    public function testKeyShapeIsStable(): void
    {
        self::assertSame(
            'angeo_llms_md_1_' . hash('sha256', 'blue-shirt.html'),
            $this->subject->forPath(1, 'blue-shirt.html')
        );
    }
}
