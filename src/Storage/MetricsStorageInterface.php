<?php

declare(strict_types=1);

namespace EzPhp\Metrics\Storage;

/**
 * Interface MetricsStorageInterface
 *
 * Where metric samples live. Each metric is a map of sample fields (a label set,
 * or a histogram bucket/sum/count of one) to float values. The in-memory storage
 * keeps them per process; a shared storage (APCu, Redis) lets every PHP-FPM worker
 * add to the same values and the /metrics request render all of them.
 *
 * Descriptions (type, help, buckets) are stored too, so a metric updated only in
 * other requests still renders in the one serving /metrics.
 *
 * @package EzPhp\Metrics\Storage
 */
interface MetricsStorageInterface
{
    /**
     * Atomically add $amount (may be negative) to a sample, starting from 0.
     *
     * @param string $metric
     * @param string $field
     * @param float  $amount
     *
     * @return void
     */
    public function add(string $metric, string $field, float $amount): void;

    /**
     * @param string $metric
     * @param string $field
     * @param float  $value
     *
     * @return void
     */
    public function set(string $metric, string $field, float $value): void;

    /**
     * All samples of a metric.
     *
     * @param string $metric
     *
     * @return array<string, float> field → value
     */
    public function fields(string $metric): array;

    /**
     * Record a metric's description; the first description of a name wins.
     *
     * @param string                                                  $metric
     * @param array{type: string, help: string, buckets: list<float>} $description
     *
     * @return void
     */
    public function describe(string $metric, array $description): void;

    /**
     * @return array<string, array{type: string, help: string, buckets: list<float>}> metric → description
     */
    public function descriptions(): array;

    /**
     * Remove all metrics and samples (tests, resets).
     *
     * @return void
     */
    public function wipe(): void;
}
