<?php

declare(strict_types=1);

namespace EzPhp\Metrics;

use RuntimeException;

/**
 * Static facade for the metrics registry.
 *
 * Provides application-wide access to metrics without injecting
 * `MetricsRegistry` through the call stack.
 *
 * Usage:
 *
 *   Metrics::counter('http_requests_total', 'Total HTTP requests')->inc(['method' => 'GET']);
 *   Metrics::gauge('memory_bytes', 'Current memory usage')->set(memory_get_usage());
 *   Metrics::histogram('request_duration_seconds', 'Request latency')->observe(0.32);
 *
 * Must be initialised by `MetricsServiceProvider::boot()` before use.
 * Throws `RuntimeException` when called before initialisation (fail-fast).
 *
 * @package EzPhp\Metrics
 */
final class Metrics
{
    private static ?MetricsRegistry $registry = null;

    /**
     * Initialise the facade with the resolved registry instance.
     * Called by `MetricsServiceProvider::boot()`.
     */
    public static function setRegistry(MetricsRegistry $registry): void
    {
        self::$registry = $registry;
    }

    /**
     * Reset the facade — used in test tearDown to prevent state leaking.
     */
    public static function resetRegistry(): void
    {
        self::$registry = null;
    }

    /**
     * Returns (or creates) a Counter with the given name.
     *
     * @throws RuntimeException  when the facade has not been initialised
     * @throws MetricsException  on metric type conflict
     */
    public static function counter(string $name, string $help): Counter
    {
        return self::registry()->counter($name, $help);
    }

    /**
     * Returns (or creates) a Gauge with the given name.
     *
     * @throws RuntimeException  when the facade has not been initialised
     * @throws MetricsException  on metric type conflict
     */
    public static function gauge(string $name, string $help): Gauge
    {
        return self::registry()->gauge($name, $help);
    }

    /**
     * Returns (or creates) a Histogram with the given name and bucket configuration.
     *
     * @param list<float> $buckets Bucket upper bounds (excluding +Inf)
     *
     * @throws RuntimeException  when the facade has not been initialised
     * @throws MetricsException  on metric type conflict
     */
    public static function histogram(
        string $name,
        string $help,
        array $buckets = Histogram::DEFAULT_BUCKETS,
    ): Histogram {
        return self::registry()->histogram($name, $help, $buckets);
    }

    /**
     * Renders all registered metrics in Prometheus text exposition format.
     *
     * @throws RuntimeException when the facade has not been initialised
     */
    public static function render(): string
    {
        return self::registry()->render();
    }

    /**
     * Resolve the registry singleton, throwing when not initialised.
     *
     * @throws RuntimeException
     */
    private static function registry(): MetricsRegistry
    {
        if (self::$registry === null) {
            throw new RuntimeException(
                'Metrics facade is not initialised. Add MetricsServiceProvider to your application.'
            );
        }

        return self::$registry;
    }
}
