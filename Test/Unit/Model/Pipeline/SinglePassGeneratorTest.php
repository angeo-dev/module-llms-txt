<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Test\Unit\Model\Pipeline;

use Angeo\LlmsTxt\Api\Data\EntityRecordInterface;
use Angeo\LlmsTxt\Api\EntityProviderInterface;
use Angeo\LlmsTxt\Api\FormatRendererInterface;
use Angeo\LlmsTxt\Api\GenerationStatusRepositoryInterface;
use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Api\UrlResolverInterface;
use Angeo\LlmsTxt\Model\Access\RestrictedCatalogGuard;
use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\Data\EntityRecord;
use Angeo\LlmsTxt\Model\Output\FilePathResolver;
use Angeo\LlmsTxt\Model\Output\LegacyOutputCleaner;
use Angeo\LlmsTxt\Model\Output\OutputContextFactory;
use Angeo\LlmsTxt\Model\Pipeline\SinglePassGenerator;
use Magento\Framework\Event\ManagerInterface as EventManager;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Filesystem\File\WriteInterface as FileWrite;
use Magento\Framework\Lock\LockManagerInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\Store;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class SinglePassGeneratorTest extends TestCase
{
    private Config&MockObject $config;
    private LockManagerInterface&MockObject $lock;
    private GenerationStatusRepositoryInterface&MockObject $status;
    private WriteInterface&MockObject $dir;
    private RestrictedCatalogGuard&MockObject $guard;
    private Store&MockObject $store;

    protected function setUp(): void
    {
        $this->store = $this->createMock(Store::class);
        $this->store->method('getId')->willReturn(1);
        $this->store->method('getCode')->willReturn('default');
        $this->store->method('isActive')->willReturn(true);

        $this->config = $this->createMock(Config::class);
        $this->config->method('isStoreExcluded')->willReturn(false);
        $this->config->method('isLlmsTxtEnabled')->willReturn(true);
        $this->config->method('isLlmsFullTxtEnabled')->willReturn(false);
        $this->config->method('isJsonlEnabled')->willReturn(false);

        $this->lock   = $this->createMock(LockManagerInterface::class);
        $this->status = $this->createMock(GenerationStatusRepositoryInterface::class);
        $this->dir    = $this->createMock(WriteInterface::class);
        $this->guard  = $this->createMock(RestrictedCatalogGuard::class);
    }

    /**
     * @param EntityProviderInterface[] $providers
     */
    private function generator(array $providers = []): SinglePassGenerator
    {
        $storeManager = $this->createMock(StoreManagerInterface::class);
        $storeManager->method('getStores')->willReturn([$this->store]);

        $filesystem = $this->createMock(Filesystem::class);
        $filesystem->method('getDirectoryWrite')->willReturn($this->dir);

        $contextFactory = $this->createMock(OutputContextFactory::class);
        $contextFactory->method('create')->willReturn($this->createMock(OutputContextInterface::class));

        $renderer = $this->createMock(FormatRendererInterface::class);
        $renderer->method('render')->willReturn(["- line\n"]);
        $renderer->method('finish')->willReturn([]);

        return new SinglePassGenerator(
            $storeManager,
            $filesystem,
            $this->createMock(LoggerInterface::class),
            $this->config,
            $this->createMock(Emulation::class),
            $this->createMock(DesignInterface::class),
            $contextFactory,
            $this->createMock(UrlResolverInterface::class),
            $this->createMock(EventManager::class),
            $this->status,
            new FilePathResolver(),
            $this->lock,
            $this->createMock(LegacyOutputCleaner::class),
            $this->guard,
            $providers,
            ['llms_txt' => $renderer]
        );
    }

    public function testDisabledStoreHasItsFilesRemoved(): void
    {
        $this->config->method('isEnabled')->willReturn(false);
        $this->dir->method('isExist')->willReturn(true);
        $this->dir->expects(self::atLeastOnce())->method('delete');
        $this->lock->expects(self::never())->method('lock');

        $summaries = $this->generator()->generateAll();

        self::assertSame(['default'], $summaries['llms_txt']->getSkipped());
    }

    public function testBusyLockSkipsWithoutFailureAndWithoutTouchingTheOtherRun(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->lock->method('lock')->willReturn(false);
        $this->lock->expects(self::never())->method('unlock');
        $this->status->expects(self::never())->method('recordFailure');
        $this->dir->expects(self::never())->method('delete');
        $this->dir->expects(self::never())->method('openFile');

        $summaries = $this->generator()->generateAll();

        self::assertSame(['default'], $summaries['llms_txt']->getSkipped());
        self::assertFalse($summaries['llms_txt']->hasFailures());
    }

    public function testFailingProviderKeepsThePreviousFile(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->lock->method('lock')->willReturn(true);
        $this->lock->expects(self::once())->method('unlock');

        $stream = $this->createMock(FileWrite::class);
        $stream->method('write')->willReturnCallback(static fn (string $s) => strlen($s));
        $this->dir->method('openFile')->willReturn($stream);
        $this->dir->method('isExist')->willReturn(true);
        $this->dir->expects(self::never())->method('renameFile');
        $this->status->expects(self::never())->method('recordSuccess');
        $this->status->expects(self::once())->method('recordFailure');

        $provider = $this->createMock(EntityProviderInterface::class);
        $provider->method('isApplicable')->willReturn(true);
        $provider->method('provide')->willReturnCallback(static function () {
            yield new EntityRecord(type: EntityRecordInterface::TYPE_PRODUCT, entityId: 1, name: 'A', url: 'u');
            throw new \RuntimeException('DB went away');
        });

        $summaries = $this->generator([$provider])->generateAll();

        self::assertTrue($summaries['llms_txt']->hasFailures());
    }

    public function testRestrictedCatalogStoreIsSkippedAndCleaned(): void
    {
        $this->config->method('isEnabled')->willReturn(true);
        $this->guard->method('isBlocked')->willReturn(true);
        $this->guard->method('activeRestrictions')->willReturn(['B2B Shared Catalog']);
        $this->dir->method('isExist')->willReturn(true);
        $this->dir->expects(self::atLeastOnce())->method('delete');
        $this->lock->expects(self::never())->method('lock');

        $this->generator()->generateAll();
    }
}
