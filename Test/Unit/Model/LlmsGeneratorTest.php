<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Model;

use Angeo\LlmsTxt\Model\LlmsGenerator;
use Angeo\LlmsTxt\Model\Llms\Providers\StoreProvider;
use Angeo\LlmsTxt\Model\Llms\Providers\CmsPageProvider;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Io\File;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class LlmsGeneratorTest extends TestCase
{
    private StoreManagerInterface $storeManager;
    private Filesystem $filesystem;
    private File $fileIo;
    private WriteInterface $directory;

    protected function setUp(): void
    {
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->filesystem = $this->createMock(Filesystem::class);
        $this->fileIo = $this->createMock(File::class);
        $this->directory = $this->createMock(WriteInterface::class);

        $this->filesystem
            ->method('getDirectoryWrite')
            ->with(DirectoryList::MEDIA)
            ->willReturn($this->directory);
    }

    public function testGenerateWritesFileForActiveStore(): void
    {
        $store = $this->createMock(Store::class);
        $provider = $this->createMock(StoreProvider::class);

        $store->method('isActive')->willReturn(true);
        $store->method('getCode')->willReturn('default');

        $provider->method('provide')
            ->with($store)
            ->willReturn('test-content');

        $this->storeManager
            ->method('getStores')
            ->willReturn([$store]);

        $this->directory
            ->method('getAbsolutePath')
            ->with('llms_default.txt')
            ->willReturn('/media/llms_default.txt');

        $this->fileIo
            ->expects($this->once())
            ->method('write')
            ->with('/media/llms_default.txt', 'test-content', 0775);

        $generator = new LlmsGenerator(
            $this->storeManager,
            $this->filesystem,
            $this->fileIo,
            [$provider]
        );

        $generator->generate();
    }

    public function testInactiveStoreIsSkipped(): void
    {
        $store = $this->createMock(Store::class);

        $store->method('isActive')->willReturn(false);

        $this->storeManager
            ->method('getStores')
            ->willReturn([$store]);

        $this->fileIo
            ->expects($this->never())
            ->method('write');

        $generator = new LlmsGenerator(
            $this->storeManager,
            $this->filesystem,
            $this->fileIo,
            []
        );

        $generator->generate();
    }

    public function testEmptyProviderOutputDoesNotWriteFile(): void
    {
        $store = $this->createMock(Store::class);
        $provider = $this->createMock(StoreProvider::class);

        $store->method('isActive')->willReturn(true);
        $store->method('getCode')->willReturn('default');

        $provider->method('provide')
            ->with($store)
            ->willReturn('');

        $this->storeManager
            ->method('getStores')
            ->willReturn([$store]);

        $this->fileIo
            ->expects($this->never())
            ->method('write');

        $generator = new LlmsGenerator(
            $this->storeManager,
            $this->filesystem,
            $this->fileIo,
            [$provider]
        );

        $generator->generate();
    }

    public function testMultipleProvidersAreConcatenated(): void
    {
        $store = $this->createMock(Store::class);

        $provider1 = $this->createMock(StoreProvider::class);
        $provider2 = $this->createMock(CmsPageProvider::class);

        $store->method('isActive')->willReturn(true);
        $store->method('getCode')->willReturn('default');

        $provider1->method('provide')->willReturn('part1');
        $provider2->method('provide')->willReturn('part2');

        $this->storeManager
            ->method('getStores')
            ->willReturn([$store]);

        $this->directory
            ->method('getAbsolutePath')
            ->with('llms_default.txt')
            ->willReturn('/media/llms_default.txt');

        $this->fileIo
            ->expects($this->once())
            ->method('write')
            ->with('/media/llms_default.txt', 'part1part2', 0775);

        $generator = new LlmsGenerator(
            $this->storeManager,
            $this->filesystem,
            $this->fileIo,
            [$provider1, $provider2]
        );

        $generator->generate();
    }
}
