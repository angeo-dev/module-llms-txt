<?php

declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Model\Generator;

use Angeo\LlmsTxt\Api\ProviderInterface;
use Angeo\LlmsTxt\Model\LlmsGenerator;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Filesystem\File\WriteInterface as FileWriteInterface;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class AbstractGeneratorTest extends TestCase
{
    private StoreManagerInterface|MockObject $storeManager;
    private Filesystem|MockObject $filesystem;
    private WriteInterface|MockObject $directory;
    private LoggerInterface|MockObject $logger;

    protected function setUp(): void
    {
        $this->storeManager = $this->createMock(StoreManagerInterface::class);
        $this->filesystem = $this->createMock(Filesystem::class);
        $this->logger = $this->createMock(LoggerInterface::class);
        $this->directory = $this->createMock(WriteInterface::class);

        $this->filesystem->method('getDirectoryWrite')
            ->with(DirectoryList::MEDIA)
            ->willReturn($this->directory);
        $this->directory->method('create')->willReturn(true);
    }

    private function buildGenerator(array $providers = []): LlmsGenerator
    {
        return new LlmsGenerator(
            $this->storeManager,
            $this->filesystem,
            $this->logger,
            $providers,
        );
    }

    private function mockActiveStore(string $code = 'default'): Store|MockObject
    {
        $store = $this->createMock(Store::class);
        $store->method('isActive')->willReturn(true);
        $store->method('getCode')->willReturn($code);
        return $store;
    }

    // ── Skip conditions ───────────────────────────────────────────────────

    public function testInactiveStoreIsSkipped(): void
    {
        $store = $this->createMock(Store::class);
        $store->method('isActive')->willReturn(false);
        $this->storeManager->method('getStores')->willReturn([$store]);

        $this->directory->expects($this->never())->method('openFile');

        $this->buildGenerator()->generate();
    }

    public function testEmptyOutputDoesNotWriteFile(): void
    {
        $store = $this->mockActiveStore();
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('provide')->willReturn('');
        $this->storeManager->method('getStores')->willReturn([$store]);

        $this->directory->expects($this->never())->method('openFile');

        $this->buildGenerator([$provider])->generate();
    }

    public function testWhitespaceOnlyOutputDoesNotWriteFile(): void
    {
        $store = $this->mockActiveStore();
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('provide')->willReturn("   \n\n   ");
        $this->storeManager->method('getStores')->willReturn([$store]);

        $this->directory->expects($this->never())->method('openFile');

        $this->buildGenerator([$provider])->generate();
    }

    // ── File writing ──────────────────────────────────────────────────────

    public function testWritesFileForActiveStoreWithContent(): void
    {
        $store = $this->mockActiveStore('en_us');
        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('provide')->willReturn('# My Store\n\n## Products\n');
        $this->storeManager->method('getStores')->willReturn([$store]);

        $fileStream = $this->createMock(FileWriteInterface::class);
        $fileStream->expects($this->once())->method('write');
        $fileStream->method('lock')->willReturn(true);
        $fileStream->method('unlock')->willReturn(true);
        $fileStream->method('close')->willReturn(true);

        $this->directory->expects($this->once())
            ->method('openFile')
            ->with('angeo/llms/llms_en_us.txt', 'w')
            ->willReturn($fileStream);

        $this->buildGenerator([$provider])->generate();
    }

    // ── Output isolation per store ────────────────────────────────────────

    public function testOutputResetBetweenStores(): void
    {
        $store1 = $this->mockActiveStore('store1');
        $store2 = $this->mockActiveStore('store2');
        $this->storeManager->method('getStores')->willReturn([$store1, $store2]);

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('provide')->willReturnCallback(
            fn($store) => "# Content for {$store->getCode()}\n"
        );

        $writtenContent = [];
        $fileStream = $this->createMock(FileWriteInterface::class);
        $fileStream->method('lock')->willReturn(true);
        $fileStream->method('unlock')->willReturn(true);
        $fileStream->method('close')->willReturn(true);
        $fileStream->method('write')->willReturnCallback(
            function ($content) use (&$writtenContent) {
                $writtenContent[] = $content;
            }
        );

        $this->directory->method('openFile')->willReturn($fileStream);

        $this->buildGenerator([$provider])->generate();

        $this->assertCount(2, $writtenContent);
        // Each file should contain ONLY its store's content, not both
        $this->assertStringContainsString('store1', $writtenContent[0]);
        $this->assertStringNotContainsString('store2', $writtenContent[0]);
        $this->assertStringContainsString('store2', $writtenContent[1]);
        $this->assertStringNotContainsString('store1', $writtenContent[1]);
    }

    // ── Multiple providers concatenated ───────────────────────────────────

    public function testMultipleProvidersAreConcatenated(): void
    {
        $store = $this->mockActiveStore();
        $this->storeManager->method('getStores')->willReturn([$store]);

        $p1 = $this->createMock(ProviderInterface::class);
        $p1->method('provide')->willReturn("# Store\n\n");
        $p2 = $this->createMock(ProviderInterface::class);
        $p2->method('provide')->willReturn("## Products\n- [Widget](url)\n");

        $written = null;
        $fileStream = $this->createMock(FileWriteInterface::class);
        $fileStream->method('lock')->willReturn(true);
        $fileStream->method('unlock')->willReturn(true);
        $fileStream->method('close')->willReturn(true);
        $fileStream->method('write')->willReturnCallback(function ($c) use (&$written) {
            $written = $c;
        });
        $this->directory->method('openFile')->willReturn($fileStream);

        $this->buildGenerator([$p1, $p2])->generate();

        $this->assertStringContainsString('# Store', $written);
        $this->assertStringContainsString('## Products', $written);
    }

    // ── Provider exception safety ─────────────────────────────────────────

    public function testFailingProviderLogsWarningAndContinues(): void
    {
        $store = $this->mockActiveStore();
        $this->storeManager->method('getStores')->willReturn([$store]);

        $badProvider = $this->createMock(ProviderInterface::class);
        $badProvider->method('provide')->willThrowException(new \RuntimeException('DB connection failed'));

        $goodProvider = $this->createMock(ProviderInterface::class);
        $goodProvider->method('provide')->willReturn("# Store\n\n");

        $this->logger->expects($this->once())->method('warning');

        $fileStream = $this->createMock(FileWriteInterface::class);
        $fileStream->method('lock')->willReturn(true);
        $fileStream->method('unlock')->willReturn(true);
        $fileStream->method('close')->willReturn(true);
        $fileStream->method('write')->willReturn(null);
        $this->directory->method('openFile')->willReturn($fileStream);

        // Should not throw — exception is caught, remaining provider runs
        $this->buildGenerator([$badProvider, $goodProvider])->generate();
    }

    // ── Single store by code ──────────────────────────────────────────────

    public function testGenerateByStoreCode(): void
    {
        $store = $this->mockActiveStore('fr');
        $this->storeManager->method('getStore')->with('fr')->willReturn($store);
        // getStores should NOT be called
        $this->storeManager->expects($this->never())->method('getStores');

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('provide')->willReturn("# French Store\n");

        $fileStream = $this->createMock(FileWriteInterface::class);
        $fileStream->method('lock')->willReturn(true);
        $fileStream->method('unlock')->willReturn(true);
        $fileStream->method('close')->willReturn(true);
        $fileStream->method('write')->willReturn(null);
        $this->directory->method('openFile')->willReturn($fileStream);

        $this->buildGenerator([$provider])->generate('fr');
    }

    // ── File extension ────────────────────────────────────────────────────

    public function testTxtExtensionUsedByLlmsGenerator(): void
    {
        $store = $this->mockActiveStore('default');
        $this->storeManager->method('getStores')->willReturn([$store]);

        $provider = $this->createMock(ProviderInterface::class);
        $provider->method('provide')->willReturn("# Store\n");

        $fileStream = $this->createMock(FileWriteInterface::class);
        $fileStream->method('lock')->willReturn(true);
        $fileStream->method('unlock')->willReturn(true);
        $fileStream->method('close')->willReturn(true);
        $fileStream->method('write')->willReturn(null);

        $this->directory->expects($this->once())
            ->method('openFile')
            ->with($this->stringContains('.txt'))
            ->willReturn($fileStream);

        $this->buildGenerator([$provider])->generate();
    }
}
