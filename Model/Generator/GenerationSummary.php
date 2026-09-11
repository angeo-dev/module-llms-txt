<?php
/**
 * @package   Angeo_LlmsTxt
 * @copyright Copyright (c) Angeo
 * @license   MIT
 */
declare(strict_types=1);

namespace Angeo\LlmsTxt\Model\Generator;

/**
 * Mutable summary collected during one generation run, keyed by store.
 *
 * Used by the CLI command and admin status panel to report per-store outcomes
 * without re-querying the status repository.
 *
 * @since 3.0.0
 */
class GenerationSummary
{
    /** @var array<string, array{bytes: int, items: int, duration: float}> */
    private array $successes = [];

    /** @var array<string, string> */
    private array $failures = [];

    /** @var string[] */
    private array $skipped = [];

    public function success(string $storeCode, int $bytes, int $items, float $duration): void
    {
        $this->successes[$storeCode] = ['bytes' => $bytes, 'items' => $items, 'duration' => $duration];
    }

    public function failure(string $storeCode, string $error): void
    {
        $this->failures[$storeCode] = $error;
    }

    public function skip(string $storeCode): void
    {
        $this->skipped[] = $storeCode;
    }

    /**
     * @return array<string, array{bytes: int, items: int, duration: float}>
     */
    public function getSuccesses(): array
    {
        return $this->successes;
    }

    /**
     * @return array<string, string>
     */
    public function getFailures(): array
    {
        return $this->failures;
    }

    /**
     * @return string[]
     */
    public function getSkipped(): array
    {
        return $this->skipped;
    }

    public function hasFailures(): bool
    {
        return $this->failures !== [];
    }

    public function getTotalBytes(): int
    {
        return array_sum(array_column($this->successes, 'bytes'));
    }

    public function getTotalItems(): int
    {
        return array_sum(array_column($this->successes, 'items'));
    }
}
