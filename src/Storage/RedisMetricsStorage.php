<?php

declare(strict_types=1);

namespace EzPhp\Metrics\Storage;

use Redis;

/**
 * Class RedisMetricsStorage
 *
 * Shared storage in Redis, for several PHP-FPM workers or hosts: one hash per
 * metric (`<prefix>m:<name>`, field → value) updated with HINCRBYFLOAT/HSET,
 * which are atomic, and one hash of JSON descriptions (`<prefix>meta`).
 *
 * Requires the PHP `ext-redis` extension.
 *
 * @package EzPhp\Metrics\Storage
 */
final readonly class RedisMetricsStorage implements MetricsStorageInterface
{
    /**
     * @param Redis  $redis
     * @param string $prefix Namespace for the keys, so they don't collide with application data.
     */
    public function __construct(
        private Redis $redis,
        private string $prefix = 'ez-php:metrics:',
    ) {
    }

    /**
     * {@inheritdoc}
     */
    public function add(string $metric, string $field, float $amount): void
    {
        $this->redis->hIncrByFloat($this->prefix . 'm:' . $metric, $field, $amount);
    }

    /**
     * {@inheritdoc}
     */
    public function set(string $metric, string $field, float $value): void
    {
        $this->redis->hSet($this->prefix . 'm:' . $metric, $field, (string) $value);
    }

    /**
     * {@inheritdoc}
     */
    public function fields(string $metric): array
    {
        $raw = $this->redis->hGetAll($this->prefix . 'm:' . $metric);
        $fields = [];

        if (is_array($raw)) {
            foreach ($raw as $field => $value) {
                if (is_numeric($value)) {
                    $fields[(string) $field] = (float) $value;
                }
            }
        }

        return $fields;
    }

    /**
     * {@inheritdoc}
     */
    public function describe(string $metric, array $description): void
    {
        $this->redis->hSetNx($this->prefix . 'meta', $metric, json_encode($description, JSON_THROW_ON_ERROR));
    }

    /**
     * {@inheritdoc}
     */
    public function descriptions(): array
    {
        $raw = $this->redis->hGetAll($this->prefix . 'meta');
        $descriptions = [];

        if (is_array($raw)) {
            foreach ($raw as $metric => $json) {
                $description = MetricDescription::decode($json);

                if ($description !== null) {
                    $descriptions[(string) $metric] = $description;
                }
            }
        }

        return $descriptions;
    }

    /**
     * {@inheritdoc}
     */
    public function wipe(): void
    {
        $keys = [$this->prefix . 'meta'];

        foreach (array_keys($this->descriptions()) as $metric) {
            $keys[] = $this->prefix . 'm:' . $metric;
        }

        $this->redis->del($keys);
    }
}
