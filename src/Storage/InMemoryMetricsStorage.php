<?php

declare(strict_types=1);

namespace EzPhp\Metrics\Storage;

/**
 * Class InMemoryMetricsStorage
 *
 * Per-process storage — the default. Values live as long as the PHP process:
 * fine for long-running processes (workers, a Fiber server), reset on every
 * request under PHP-FPM.
 *
 * @package EzPhp\Metrics\Storage
 */
final class InMemoryMetricsStorage implements MetricsStorageInterface
{
    /**
     * @var array<string, array<string, float>>
     */
    private array $values = [];

    /**
     * @var array<string, array{type: string, help: string, buckets: list<float>}>
     */
    private array $descriptions = [];

    public function add(string $metric, string $field, float $amount): void
    {
        $this->values[$metric][$field] = ($this->values[$metric][$field] ?? 0.0) + $amount;
    }

    public function set(string $metric, string $field, float $value): void
    {
        $this->values[$metric][$field] = $value;
    }

    public function fields(string $metric): array
    {
        return $this->values[$metric] ?? [];
    }

    public function describe(string $metric, array $description): void
    {
        $this->descriptions[$metric] ??= $description;
    }

    public function descriptions(): array
    {
        return $this->descriptions;
    }

    public function wipe(): void
    {
        $this->values = [];
        $this->descriptions = [];
    }
}
