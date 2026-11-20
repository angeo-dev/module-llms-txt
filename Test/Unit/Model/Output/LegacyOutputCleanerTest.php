<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Model\Output;

use Angeo\LlmsTxt\Model\Output\FilePathResolver;
use Angeo\LlmsTxt\Model\Output\LegacyOutputCleaner;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class LegacyOutputCleanerTest extends TestCase
{
    public function testOutputDirectoryIsNotWebServed(): void
    {
        self::assertSame(DirectoryList::VAR_DIR, FilePathResolver::DIRECTORY);
    }

    public function testRemovesOldPublicFiles(): void
    {
        $media = $this->createMock(WriteInterface::class);
        $media->method('isExist')->with('angeo/llms')->willReturn(true);
        $media->method('read')->willReturn(['angeo/llms/llms_default.txt', 'angeo/llms/llms_default.jsonl']);
        $media->method('isFile')->willReturn(true);
        $media->expects(self::once())->method('delete')->with('angeo/llms');

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('getDirectoryWrite')->with(DirectoryList::MEDIA)->willReturn($media);

        self::assertSame(2, (new LegacyOutputCleaner($filesystem, $this->createMock(LoggerInterface::class)))->clean());
    }

    public function testNothingToDo(): void
    {
        $media = $this->createMock(WriteInterface::class);
        $media->method('isExist')->willReturn(false);
        $media->expects(self::never())->method('delete');

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('getDirectoryWrite')->willReturn($media);

        self::assertSame(0, (new LegacyOutputCleaner($filesystem, $this->createMock(LoggerInterface::class)))->clean());
    }
}
