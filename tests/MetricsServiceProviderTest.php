<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Metrics\Counter;
use EzPhp\Metrics\Metrics;
use EzPhp\Metrics\MetricsRegistry;
use EzPhp\Metrics\MetricsServiceProvider;
use EzPhp\Metrics\Storage\InMemoryMetricsStorage;
use EzPhp\Metrics\Storage\RedisMetricsStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Support\FakeConfig;
use Tests\Support\FakeContainer;
use Tests\Support\MetricsRecordingRouter;

/**
 * Smoke test: MetricsServiceProvider registers and boots its bindings in a
 * minimal container context without error.
 *
 * @uses \Tests\Support\FakeConfig
 * @uses \Tests\Support\FakeContainer
 * @uses \Tests\Support\MetricsRecordingRouter
 */
#[CoversClass(MetricsServiceProvider::class)]
#[UsesClass(InMemoryMetricsStorage::class)]
#[UsesClass(RedisMetricsStorage::class)]
#[UsesClass(MetricsRegistry::class)]
#[UsesClass(Metrics::class)]
#[UsesClass(Counter::class)]
final class MetricsServiceProviderTest extends TestCase
{
    protected function tearDown(): void
    {
        Metrics::resetRegistry();
        parent::tearDown();
    }

    public function test_register_binds_metrics_registry(): void
    {
        $container = new FakeContainer(new FakeConfig([]));
        $provider = new MetricsServiceProvider($container);

        $provider->register();

        $this->assertTrue($container->wasBound(MetricsRegistry::class));
        $this->assertInstanceOf(MetricsRegistry::class, $container->make(MetricsRegistry::class));
    }

    public function test_boot_initialises_metrics_facade(): void
    {
        $container = new FakeContainer(new FakeConfig([]));
        $provider = new MetricsServiceProvider($container);

        $provider->register();
        $provider->boot(); // Router not bound — route registration is skipped silently.

        // The facade is wired after boot — registering a counter goes through the registry.
        $this->assertInstanceOf(Counter::class, Metrics::counter('smoke_total', 'Smoke test counter'));
    }

    public function test_boot_registers_the_default_metrics_route(): void
    {
        $router = $this->bootWithConfig([]);

        $this->assertSame(['/metrics'], $router->getPaths);
    }

    public function test_endpoint_is_read_from_config(): void
    {
        $router = $this->bootWithConfig(['metrics.endpoint' => '/internal/metrics']);

        $this->assertSame(['/internal/metrics'], $router->getPaths);
    }

    public function test_endpoint_false_skips_route_registration(): void
    {
        $router = $this->bootWithConfig(['metrics.endpoint' => false]);

        $this->assertSame([], $router->getPaths);
        // The façade is still wired.
        $this->assertInstanceOf(Counter::class, Metrics::counter('no_route_total', 'Counter'));
    }

    public function test_empty_endpoint_skips_route_registration(): void
    {
        $router = $this->bootWithConfig(['metrics.endpoint' => '']);

        $this->assertSame([], $router->getPaths);
    }

    /**
     * @param array<string, mixed> $config
     */
    private function bootWithConfig(array $config): MetricsRecordingRouter
    {
        $router = new MetricsRecordingRouter();
        $container = new FakeContainer(new FakeConfig($config));
        $container->instance(\EzPhp\Contracts\RouterInterface::class, $router);
        $provider = new MetricsServiceProvider($container);

        $provider->register();
        $provider->boot();

        return $router;
    }

    public function test_storage_defaults_to_memory_and_redis_is_selectable(): void
    {
        $memory = new FakeContainer(new FakeConfig([]));
        (new MetricsServiceProvider($memory))->register();
        $this->assertInstanceOf(\EzPhp\Metrics\Storage\InMemoryMetricsStorage::class, $memory->make(MetricsRegistry::class)->storage());

        if (!extension_loaded('redis')) {
            return;
        }

        $redis = new FakeContainer(new FakeConfig([
            'metrics.storage' => 'redis',
            'metrics.redis.host' => getenv('REDIS_HOST') ?: 'redis',
            'metrics.redis.port' => '6379',
            'metrics.redis.database' => 5,
        ]));
        (new MetricsServiceProvider($redis))->register();
        $this->assertInstanceOf(\EzPhp\Metrics\Storage\RedisMetricsStorage::class, $redis->make(MetricsRegistry::class)->storage());
    }
}
