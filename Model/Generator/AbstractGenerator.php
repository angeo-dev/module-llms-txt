<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Generator;

use Angeo\LlmsTxt\Api\GenerationStatusRepositoryInterface;
use Angeo\LlmsTxt\Api\OutputContextInterface;
use Angeo\LlmsTxt\Api\ProviderInterface;
use Angeo\LlmsTxt\Api\UrlResolverInterface;
use Angeo\LlmsTxt\Model\Config;
use Angeo\LlmsTxt\Model\Output\OutputContextFactory;
use Angeo\LlmsTxt\Model\Output\AgentSurfacesBlock;
use Angeo\LlmsTxt\Model\Output\Signature;
use Angeo\LlmsTxt\Model\Output\SurfaceRegistry;
use Magento\Framework\App\Area;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Event\ManagerInterface as EventManagerInterface;
use Magento\Framework\Exception\FileSystemException;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\View\DesignInterface;
use Magento\Store\Api\Data\StoreInterface;
use Magento\Store\Model\App\Emulation;
use Magento\Store\Model\StoreManagerInterface;
use Psr\Log\LoggerInterface;

/**
 * Base class for all file generators.
 *
 * Responsibilities:
 *  - iterate active, non-excluded stores
 *  - set up frontend emulation per store (with the design-area guard)
 *  - warm the URL resolver once per store
 *  - build an {@see OutputContextInterface}
 *  - stream each provider's output to a temp file under a file lock
 *  - atomically rename .tmp → final on success
 *  - clean up stale files for stores that became inactive / excluded
 *  - record status + dispatch events
 *
 * Subclasses only need to declare the format, verbosity, and extension.
 *
 * @since 3.0.0
 * @deprecated 3.2.0 Legacy per-format pipeline. Superseded by the single-pass
 *             pipeline ({@see \Angeo\LlmsTxt\Model\Pipeline\SinglePassGenerator}
 *             + {@see \Angeo\LlmsTxt\Api\EntityProviderInterface}). Kept fully
 *             functional while `generation_mode = legacy`; WILL BE REMOVED in
 *             4.0.0 together with that mode.
 * @see \Angeo\LlmsTxt\Model\Pipeline\SinglePassGenerator
 */
abstract class AbstractGenerator
{
    /** @var int  Generations only — never used at frontend serve time. */
    private const LOCK_WAIT_SECONDS = 0;

    /**
     * @param ProviderInterface[] $providers
     */
    public function __construct(
        protected readonly StoreManagerInterface         $storeManager,
        protected readonly Filesystem                    $filesystem,
        protected readonly LoggerInterface               $logger,
        protected readonly Config                        $config,
        protected readonly Emulation                     $emulation,
        protected readonly DesignInterface               $viewDesign,
        protected readonly OutputContextFactory          $contextFactory,
        protected readonly UrlResolverInterface          $urlResolver,
        protected readonly EventManagerInterface         $eventManager,
        protected readonly GenerationStatusRepositoryInterface $statusRepository,
        protected readonly array                         $providers = [],
        ?Signature                                       $signature = null,
        ?AgentSurfacesBlock                              $surfacesBlock = null,
        ?SurfaceRegistry                                 $surfaceRegistry = null
    ) {
        // Optional + lazily defaulted so third-party subclasses compiled against
        // the 3.0–3.2 constructor signature keep working (this class is
        // @deprecated but must stay BC until 4.0.0).
        $this->signature = $signature ?? new Signature();
        $this->surfacesBlock = $surfacesBlock;
        $this->surfaceRegistry = $surfaceRegistry;
    }

    /** @since 3.3.0 */
    private readonly Signature $signature;
    /** @since 3.4.0 */
    private readonly ?AgentSurfacesBlock $surfacesBlock;
    /** @since 3.4.0 */
    private readonly ?SurfaceRegistry $surfaceRegistry;

    /**
     * Output format constant — one of {@see OutputContextInterface}::FORMAT_*.
     */
    abstract protected function getFormat(): string;

    /**
     * Verbosity level — one of {@see OutputContextInterface}::VERBOSITY_*.
     */
    abstract protected function getVerbosity(): string;

    /**
     * File extension (without dot): 'txt', 'jsonl', etc.
     */
    abstract protected function getExtension(): string;

    /**
     * Per-store config gate. Subclasses check the right "is this format enabled" flag.
     */
    abstract protected function isFormatEnabled(StoreInterface $store): bool;

    /**
     * The base name of the output file (without extension), defaulting to "llms".
     * Override in subclasses (e.g. "llms-full") to differentiate.
     */
    protected function getFileBaseName(): string
    {
        return 'llms';
    }

    protected function getSubDir(): string
    {
        return 'angeo/llms';
    }

    /**
     * Generate files for all eligible stores (or a single store if storeCode given).
     *
     * @throws FileSystemException
     */
    public function generate(?string $storeCode = null): GenerationSummary
    {
        $summary = new GenerationSummary();

        if (!$this->config->isEnabled()) {
            $this->logger->info('[Angeo LlmsTxt] Module is disabled — generation skipped.');
            return $summary;
        }

        $stores = $storeCode
            ? [$this->storeManager->getStore($storeCode)]
            : $this->storeManager->getStores();

        $directory = $this->filesystem->getDirectoryWrite(DirectoryList::MEDIA);
        $directory->create($this->getSubDir());

        foreach ($stores as $store) {
            $this->processStore($store, $directory, $summary);
        }

        return $summary;
    }

    private function processStore(
        StoreInterface $store,
        WriteInterface $directory,
        GenerationSummary $summary
    ): void {
        // Stores that are inactive, excluded, or have this format disabled get their
        // stale file removed (so previously-generated content doesn't keep being served).
        // `isActive()` is not declared on StoreInterface but exists on the concrete Store
        // model that StoreManager always returns — guard defensively for future-proofing.
        $isActive = !method_exists($store, 'isActive') || $store->isActive();
        if (!$isActive
            || $this->config->isStoreExcluded($store)
            || !$this->isFormatEnabled($store)
        ) {
            $this->deleteStaleFile($store, $directory);
            $summary->skip($store->getCode());
            return;
        }

        $emulated = false;
        $startedAt = microtime(true);
        try {
            // Design area must be set before emulation or the restoration on stop()
            // throws a TypeError on null initialDesign.
            if (!$this->viewDesign->getArea()) {
                $this->viewDesign->setArea(Area::AREA_FRONTEND);
            }

            $this->emulation->startEnvironmentEmulation(
                (int) $store->getId(),
                Area::AREA_FRONTEND,
                true
            );
            $emulated = true;

            $this->urlResolver->reset();
            $this->urlResolver->warmUp((int) $store->getId());

            $context = $this->contextFactory->create(
                $store,
                $this->getFormat(),
                $this->getVerbosity()
            );

            $this->eventManager->dispatch('angeo_llms_generation_before', [
                'store'   => $store,
                'format'  => $this->getFormat(),
                'context' => $context,
            ]);

            $result = $this->writeFile($store, $directory, $context);

            $duration = microtime(true) - $startedAt;
            $this->statusRepository->recordSuccess(
                $store->getCode(),
                $this->getFormat(),
                $result['bytes'],
                $result['items'],
                $duration
            );

            $this->eventManager->dispatch('angeo_llms_generation_after', [
                'store'    => $store,
                'format'   => $this->getFormat(),
                'file'     => $result['path'],
                'bytes'    => $result['bytes'],
                'items'    => $result['items'],
                'duration' => $duration,
            ]);

            $this->logger->info(sprintf(
                '[Angeo LlmsTxt] Generated %s for store %s (%d bytes, %d items, %.2fs)',
                $result['path'],
                $store->getCode(),
                $result['bytes'],
                $result['items'],
                $duration
            ));

            $summary->success($store->getCode(), $result['bytes'], $result['items'], $duration);
        } catch (\Throwable $e) {
            $this->statusRepository->recordFailure(
                $store->getCode(),
                $this->getFormat(),
                $e->getMessage()
            );

            $this->eventManager->dispatch('angeo_llms_generation_failed', [
                'store'  => $store,
                'format' => $this->getFormat(),
                'error'  => $e->getMessage(),
            ]);

            $this->logger->error(sprintf(
                '[Angeo LlmsTxt] Failed to generate %s for store %s: %s',
                $this->getFormat(),
                $store->getCode(),
                $e->getMessage()
            ), ['exception' => $e]);

            $summary->failure($store->getCode(), $e->getMessage());
        } finally {
            if ($emulated) {
                $this->emulation->stopEnvironmentEmulation();
            }
            $this->urlResolver->reset();
        }
    }

    /**
     * Stream every provider's chunks to a temp file, then atomically rename.
     *
     * @return array{path: string, bytes: int, items: int}
     */
    private function writeFile(
        StoreInterface $store,
        WriteInterface $directory,
        OutputContextInterface $context
    ): array {
        $finalPath = $this->getRelativeFilePath($store);
        $tmpPath   = $finalPath . '.tmp';
        $lockPath  = $finalPath . '.lock';

        // Lock-file pattern: open with 'c+' so we don't truncate. Use LOCK_EX | LOCK_NB
        // so a second concurrent run logs and exits rather than corrupting the output.
        $lockHandle = $this->acquireLock($directory, $lockPath);
        if ($lockHandle === null) {
            throw new \RuntimeException(sprintf(
                'Generation already in progress for store %s, format %s',
                $store->getCode(),
                $this->getFormat()
            ));
        }

        $stream = null;
        $bytes  = 0;
        $items  = 0;

        try {
            $stream = $directory->openFile($tmpPath, 'w');
            foreach ($this->providers as $provider) {
                if (!$provider instanceof ProviderInterface) {
                    continue;
                }
                if (!$provider->isApplicable($context)) {
                    continue;
                }

                try {
                    foreach ($provider->provide($context) as $chunk) {
                        if (!is_string($chunk) || $chunk === '') {
                            continue;
                        }
                        $bytes += $stream->write($chunk);
                        $items++;
                    }
                } catch (\Throwable $e) {
                    $this->logger->warning(sprintf(
                        '[Angeo LlmsTxt] Provider %s failed for store %s: %s',
                        $provider::class,
                        $store->getCode(),
                        $e->getMessage()
                    ));
                }
            }
            // Agent-discovery hub block (llms.txt only) + attribution
            // signature — markdown formats only (a comment line would corrupt
            // JSONL), only when real content was written. Not counted as items.
            if ($bytes > 0 && $this->getExtension() === 'txt') {
                if ($this->getFormat() === \Angeo\LlmsTxt\Api\OutputContextInterface::FORMAT_LLMS_TXT
                    && $this->surfacesBlock !== null
                ) {
                    $block = $this->surfacesBlock->render(
                        $context->getBaseUrl(),
                        $this->surfaceRegistry !== null ? $this->surfaceRegistry->forStore($store) : []
                    );
                    if ($block !== '') {
                        $bytes += $stream->write($block);
                    }
                }
                if ($this->config->isSignatureEnabled($store)) {
                    $bytes += $stream->write($this->signature->forTxt());
                }
            }
        } finally {
            if ($stream !== null) {
                $stream->close();
            }
        }

        // Empty output → don't create the file at all.
        if ($bytes === 0) {
            if ($directory->isExist($tmpPath)) {
                $directory->delete($tmpPath);
            }
            $this->releaseLock($directory, $lockPath, $lockHandle);
            return ['path' => $finalPath, 'bytes' => 0, 'items' => 0];
        }

        // Atomic rename — guarantees readers never see a half-written file.
        $directory->renameFile($tmpPath, $finalPath);

        $this->releaseLock($directory, $lockPath, $lockHandle);

        return ['path' => $finalPath, 'bytes' => $bytes, 'items' => $items];
    }

    /**
     * Acquire an exclusive, non-blocking lock for this generation.
     * Returns the file resource on success, or null if the lock was already held.
     *
     * Why we don't use $stream->lock(): the file was opened with 'w', which
     * already truncated it BEFORE the flock — meaning two racing generations
     * both reach the lock with an empty file, both write, and the last one wins
     * unpredictably. A separate lock file with 'c+' (create-or-keep) avoids this.
     *
     * @return resource|null
     */
    private function acquireLock(WriteInterface $directory, string $lockPath): mixed
    {
        // We need a real PHP file resource (flock()) — Magento's stream wrappers
        // don't expose one. Resolve to an absolute path.
        $absolute = $directory->getAbsolutePath($lockPath);

        // Ensure the parent dir exists.
        $parent = dirname($lockPath);
        if (!$directory->isExist($parent)) {
            $directory->create($parent);
        }

        $handle = fopen($absolute, 'c+');
        if ($handle === false) {
            return null;
        }

        if (!flock($handle, LOCK_EX | LOCK_NB)) {
            fclose($handle);
            return null;
        }

        return $handle;
    }

    /**
     * @param resource $handle
     */
    private function releaseLock(WriteInterface $directory, string $lockPath, mixed $handle): void
    {
        if (is_resource($handle)) {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
        // Best-effort cleanup of the lock file itself.
        try {
            if ($directory->isExist($lockPath)) {
                $directory->delete($lockPath);
            }
        } catch (\Throwable) {
            // ignore
        }
    }

    /**
     * Registered legacy providers — exposed so the single-pass pipeline can
     * detect and adapt third-party providers (BC compatibility pass).
     *
     * @return \Angeo\LlmsTxt\Api\ProviderInterface[]
     * @since 3.2.0
     */
    public function getProviders(): array
    {
        return $this->providers;
    }

    public function getFilePath(string $storeCode): string
    {
        $directory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        return $directory->getAbsolutePath($this->getRelativeFilePath($storeCode));
    }

    /**
     * Media-relative path of the generated file — preferred over
     * {@see getFilePath()} for callers that read through the Filesystem
     * abstraction (remote-storage / Adobe Commerce Cloud compatible).
     *
     * @since 3.1.0
     */
    public function getRelativePath(string $storeCode): string
    {
        return $this->getRelativeFilePath($storeCode);
    }

    public function fileExists(string $storeCode): bool
    {
        $directory = $this->filesystem->getDirectoryRead(DirectoryList::MEDIA);
        return $directory->isExist($this->getRelativeFilePath($storeCode));
    }

    private function getRelativeFilePath(string|StoreInterface $store): string
    {
        $code = $store instanceof StoreInterface ? $store->getCode() : $store;
        return sprintf(
            '%s/%s_%s.%s',
            $this->getSubDir(),
            $this->getFileBaseName(),
            $code,
            $this->getExtension()
        );
    }

    private function deleteStaleFile(StoreInterface $store, WriteInterface $directory): void
    {
        $relativePath = $this->getRelativeFilePath($store);
        if (!$directory->isExist($relativePath)) {
            return;
        }
        try {
            $directory->delete($relativePath);
            $this->logger->info(sprintf(
                '[Angeo LlmsTxt] Deleted stale %s for store %s',
                $relativePath,
                $store->getCode()
            ));
        } catch (\Throwable $e) {
            $this->logger->warning(sprintf(
                '[Angeo LlmsTxt] Could not delete stale file %s: %s',
                $relativePath,
                $e->getMessage()
            ));
        }
    }
}
