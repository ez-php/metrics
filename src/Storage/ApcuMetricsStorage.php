<?php

declare(strict_types=1);

namespace EzPhp\Metrics\Storage;

use APCUIterator;
use RuntimeException;

/**
 * Class ApcuMetricsStorage
 *
 * Shared storage in APCu — for the PHP-FPM workers of one host, no extra service.
 * Every sample is its own APCu entry. APCu can only increment integers atomically,
 * so values are stored as fixed-point integers with 6 decimal places (`apcu_inc`):
 * increments smaller than 0.000001 are lost. Samples are found again by key prefix
 * with APCUIterator.
 *
 * Requires `ext-apcu` (and `apc.enable_cli=1` for CLI use). APCu memory is per
 * host and cleared on restart; use RedisMetricsStorage across hosts.
 *
 * @package EzPhp\Metrics\Storage
 */
final readonly class ApcuMetricsStorage implements MetricsStorageInterface
{
    /**
     * Fixed-point scale: values are stored as round(value * SCALE).
     */
    private const int SCALE = 1_000_000;

    /**
     * @param string $prefix
     *
     * @throws RuntimeException When ext-apcu is not loaded or enabled.
     */
    public function __construct(private string $prefix = 'ez-php:metrics:')
    {
        if (!extension_loaded('apcu') || !apcu_enabled()) {
            throw new RuntimeException('ApcuMetricsStorage requires ext-apcu to be loaded and enabled (apc.enable_cli=1 on the CLI).');
        }
    }

    /**
     * {@inheritdoc}
     */
    public function add(string $metric, string $field, float $amount): void
    {
        $key = $this->key($metric, $field);
        $step = (int) round($amount * self::SCALE);

        apcu_add($key, 0);
        apcu_inc($key, $step);
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $metric, string $field, float $value): void
    {
        apcu_store($this->key($metric, $field), (int) round($value * self::SCALE));
    }

    /**
     * {@inheritdoc}
     */
    public function fields(string $metric): array
    {
        $prefix = $this->prefix . 'm:' . $metric . "\0";
        $fields = [];

        foreach (new APCUIterator('/^' . preg_quote($prefix, '/') . '/', APC_ITER_KEY | APC_ITER_VALUE) as $entry) {
            if (is_array($entry) && is_string($entry['key'] ?? null) && is_int($entry['value'] ?? null)) {
                $fields[substr($entry['key'], strlen($prefix))] = $entry['value'] / self::SCALE;
            }
        }

        return $fields;
    }

    /**
     * {@inheritdoc}
     */
    public function describe(string $metric, array $description): void
    {
        apcu_add($this->prefix . 'meta' . "\0" . $metric, json_encode($description, JSON_THROW_ON_ERROR));
    }

    /**
     * {@inheritdoc}
     */
    public function descriptions(): array
    {
        $prefix = $this->prefix . 'meta' . "\0";
        $descriptions = [];

        foreach (new APCUIterator('/^' . preg_quote($prefix, '/') . '/', APC_ITER_KEY | APC_ITER_VALUE) as $entry) {
            if (!is_array($entry) || !is_string($entry['key'] ?? null)) {
                continue;
            }

            $description = MetricDescription::decode($entry['value'] ?? null);

            if ($description !== null) {
                $descriptions[substr($entry['key'], strlen($prefix))] = $description;
            }
        }

        return $descriptions;
    }

    /**
     * {@inheritdoc}
     */
    public function wipe(): void
    {
        apcu_delete(new APCUIterator('/^' . preg_quote($this->prefix, '/') . '/'));
    }

    private function key(string $metric, string $field): string
    {
        return $this->prefix . 'm:' . $metric . "\0" . $field;
    }
}
