<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Repository;

use Angeo\LlmsTxt\Api\Data\GenerationStatusInterface;
use Angeo\LlmsTxt\Api\GenerationStatusRepositoryInterface;
use Angeo\LlmsTxt\Model\Data\GenerationStatus;
use Magento\Framework\App\Filesystem\DirectoryList;
use Magento\Framework\Filesystem;
use Magento\Framework\Filesystem\Directory\WriteInterface;
use Magento\Framework\Serialize\SerializerInterface;
use Psr\Log\LoggerInterface;

/**
 * Tracks per-store/per-format generation status in a small JSON file under
 * var/angeo_llms/status.json.
 *
 * We deliberately avoid core_config_data because:
 *  - Writes there bust the config cache on every generation pass (expensive).
 *  - Several rows × stores can quickly fill up the audit-log of monitoring tools.
 *
 * Using a single var/ JSON file keeps writes cheap and removes any setup_module
 * dependency. The file is created on first write; reads tolerate a missing file.
 *
 * Concurrency (3.1.0): three generators × cron × CLI can update the file in
 * parallel. Every mutation now runs as a locked read-modify-write (flock on a
 * sidecar .lock file) followed by an atomic tmp-rename, so concurrent passes
 * can no longer lose each other's updates or leave a truncated JSON file.
 *
 * @since 3.0.0
 */
class GenerationStatusRepository implements GenerationStatusRepositoryInterface
{
    private const DIR  = 'angeo_llms';
    private const FILE = 'angeo_llms/status.json';
    private const LOCK = 'angeo_llms/status.json.lock';

    private ?WriteInterface $directory = null;
    /** @var array<string, array<string, mixed>>|null */
    private ?array $data = null;

    public function __construct(
        private readonly Filesystem $filesystem,
        private readonly SerializerInterface $serializer,
        private readonly LoggerInterface $logger
    ) {
    }

    public function get(string $storeCode, string $format): ?GenerationStatusInterface
    {
        $data = $this->load();
        $row  = $data[$this->key($storeCode, $format)] ?? null;
        if ($row === null) {
            return null;
        }
        return $this->hydrate($row);
    }

    public function recordSuccess(
        string $storeCode,
        string $format,
        int $byteSize,
        int $itemCount,
        float $durationSeconds
    ): void {
        $now = time();
        $this->mutate($storeCode, $format, [
            'status'            => GenerationStatusInterface::STATUS_SUCCESS,
            'last_attempt_at'   => $now,
            'last_success_at'   => $now,
            'byte_size'         => $byteSize,
            'item_count'        => $itemCount,
            'duration_seconds'  => $durationSeconds,
            'error_message'     => null,
        ]);
    }

    public function recordFailure(string $storeCode, string $format, string $errorMessage): void
    {
        $existing = $this->load()[$this->key($storeCode, $format)] ?? [];
        $this->mutate($storeCode, $format, [
            'status'            => GenerationStatusInterface::STATUS_FAILED,
            'last_attempt_at'   => time(),
            'last_success_at'   => $existing['last_success_at'] ?? null,
            'byte_size'         => (int) ($existing['byte_size'] ?? 0),
            'item_count'        => (int) ($existing['item_count'] ?? 0),
            'duration_seconds'  => (float) ($existing['duration_seconds'] ?? 0.0),
            'error_message'     => $errorMessage,
        ]);
    }

    public function getAll(): array
    {
        $data = $this->load();
        $out = [];
        foreach ($data as $row) {
            $out[] = $this->hydrate($row);
        }
        return $out;
    }

    /**
     * Locked read-modify-write: reload the on-disk state INSIDE the lock so a
     * concurrent generator's update made since our last read is not clobbered.
     */
    private function mutate(string $storeCode, string $format, array $row): void
    {
        $dir = $this->getDirectory();
        $lockHandle = null;

        try {
            $dir->create(self::DIR);
            $lockHandle = $this->acquireLock($dir);

            // Re-read under the lock — drop any stale in-memory copy.
            $this->data = null;
            $data = $this->load();
            $data[$this->key($storeCode, $format)] = array_merge(
                ['store_code' => $storeCode, 'format' => $format],
                $row
            );
            $this->data = $data;

            // Atomic write: tmp + rename so readers never observe a torn file.
            $tmp = self::FILE . '.tmp';
            $dir->writeFile($tmp, (string) $this->serializer->serialize($this->data));
            $dir->renameFile($tmp, self::FILE);
        } catch (\Throwable $e) {
            $this->logger->warning(sprintf(
                '[Angeo LlmsTxt] Could not write generation status file: %s',
                $e->getMessage()
            ));
        } finally {
            $this->releaseLock($lockHandle);
        }
    }

    /**
     * Blocking exclusive lock — status writes are tiny, so waiting briefly is
     * preferable to dropping an update.
     *
     * @return resource|null
     */
    private function acquireLock(WriteInterface $dir): mixed
    {
        $absolute = $dir->getAbsolutePath(self::LOCK);
        $handle = fopen($absolute, 'c+');
        if ($handle === false) {
            return null;
        }
        if (!flock($handle, LOCK_EX)) {
            fclose($handle);
            return null;
        }
        return $handle;
    }

    /**
     * @param resource|null $handle
     */
    private function releaseLock(mixed $handle): void
    {
        if (is_resource($handle)) {
            flock($handle, LOCK_UN);
            fclose($handle);
        }
    }

    private function key(string $storeCode, string $format): string
    {
        return $storeCode . '::' . $format;
    }

    /**
     * @return array<string, mixed>
     */
    private function load(): array
    {
        if ($this->data !== null) {
            return $this->data;
        }
        $dir = $this->getDirectory();
        try {
            if (!$dir->isExist(self::FILE)) {
                $this->data = [];
                return $this->data;
            }
            $raw = (string) $dir->readFile(self::FILE);
            $decoded = $this->serializer->unserialize($raw);
            $this->data = is_array($decoded) ? $decoded : [];
        } catch (\Throwable $e) {
            $this->logger->warning(sprintf(
                '[Angeo LlmsTxt] Could not read generation status file: %s',
                $e->getMessage()
            ));
            $this->data = [];
        }
        return $this->data;
    }

    private function getDirectory(): WriteInterface
    {
        if ($this->directory === null) {
            $this->directory = $this->filesystem->getDirectoryWrite(DirectoryList::VAR_DIR);
        }
        return $this->directory;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): GenerationStatusInterface
    {
        return new GenerationStatus(
            (string) ($row['store_code'] ?? ''),
            (string) ($row['format'] ?? ''),
            (string) ($row['status'] ?? GenerationStatusInterface::STATUS_PENDING),
            isset($row['last_attempt_at']) ? (int) $row['last_attempt_at'] : null,
            isset($row['last_success_at']) ? (int) $row['last_success_at'] : null,
            (int) ($row['byte_size'] ?? 0),
            (int) ($row['item_count'] ?? 0),
            (float) ($row['duration_seconds'] ?? 0.0),
            isset($row['error_message']) ? (string) $row['error_message'] : null
        );
    }
}
