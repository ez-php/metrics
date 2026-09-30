<?php

declare(strict_types=1);

namespace EzPhp\Metrics;

use EzPhp\Contracts\ConfigInterface;
use EzPhp\Contracts\ContainerInterface;
use EzPhp\Contracts\RouterInterface;
use EzPhp\Contracts\ServiceProvider;
use EzPhp\Metrics\Storage\ApcuMetricsStorage;
use EzPhp\Metrics\Storage\InMemoryMetricsStorage;
use EzPhp\Metrics\Storage\MetricsStorageInterface;
use EzPhp\Metrics\Storage\RedisMetricsStorage;
use Redis;

/**
 * Registers the MetricsRegistry in the container, initialises the Metrics facade,
 * and registers the metrics route.
 *
 * Config:
 *   metrics.endpoint — path of the GET route (default '/metrics'). `false` or ''
 *                      skips registration, so the application can register
 *                      MetricsController itself with its own middleware.
 *   metrics.storage  — `memory` (default, per process), `apcu` (shared by the
 *                      PHP-FPM workers of one host) or `redis` (shared across
 *                      hosts; metrics.redis.host/port/database/prefix).
 *
 * The route is only registered when the Router is available in the
 * container. In CLI or isolated test contexts where the Router is not bound
 * the route registration is silently skipped.
 *
 * @package EzPhp\Metrics
 */
final class MetricsServiceProvider extends ServiceProvider
{
    /**
     * Bind MetricsRegistry as a shared singleton in the container.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(MetricsRegistry::class, function (ContainerInterface $app): MetricsRegistry {
            return new MetricsRegistry($this->storage());
        });
    }

    /**
     * Initialise the Metrics static facade and register the metrics route.
     *
     * @return void
     */
    public function boot(): void
    {
        Metrics::setRegistry($this->app->make(MetricsRegistry::class));

        $endpoint = $this->endpoint();

        if ($endpoint === null) {
            return;
        }

        try {
            $router = $this->app->make(RouterInterface::class);
            $router->get($endpoint, [MetricsController::class, '__invoke']);
        } catch (\Throwable) {
            // Router not bound (CLI or isolated test context) — route skipped.
        }
    }

    /**
     * The storage selected by `metrics.storage`.
     *
     * @return MetricsStorageInterface
     */
    private function storage(): MetricsStorageInterface
    {
        try {
            $config = $this->app->make(ConfigInterface::class);
        } catch (\Throwable) {
            return new InMemoryMetricsStorage();
        }

        $driver = $config->get('metrics.storage', 'memory');

        if ($driver === 'apcu') {
            return new ApcuMetricsStorage(self::string($config, 'metrics.prefix', 'ez-php:metrics:'));
        }

        if ($driver === 'redis') {
            $redis = new Redis();
            $redis->connect(self::string($config, 'metrics.redis.host', '127.0.0.1'), self::int($config, 'metrics.redis.port', 6379));
            $database = self::int($config, 'metrics.redis.database', 0);

            if ($database !== 0) {
                $redis->select($database);
            }

            return new RedisMetricsStorage($redis, self::string($config, 'metrics.prefix', 'ez-php:metrics:'));
        }

        return new InMemoryMetricsStorage();
    }

    private static function string(ConfigInterface $config, string $key, string $default): string
    {
        $value = $config->get($key, $default);

        return is_string($value) && $value !== '' ? $value : $default;
    }

    private static function int(ConfigInterface $config, string $key, int $default): int
    {
        $value = $config->get($key, $default);

        return is_int($value) || (is_string($value) && ctype_digit($value)) ? (int) $value : $default;
    }

    /**
     * The configured route path, or null when auto-registration is disabled.
     *
     * @return string|null
     */
    private function endpoint(): ?string
    {
        try {
            $value = $this->app->make(ConfigInterface::class)->get('metrics.endpoint', '/metrics');
        } catch (\Throwable) {
            return '/metrics'; // Config not bound — keep the default.
        }

        if ($value === false || $value === '') {
            return null;
        }

        return is_string($value) ? $value : '/metrics';
    }
}
