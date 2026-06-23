<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Metrics\Counter;
use EzPhp\Metrics\Metrics;
use EzPhp\Metrics\MetricsRegistry;
use EzPhp\Metrics\MetricsServiceProvider;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use Tests\Support\FakeConfig;
use Tests\Support\FakeContainer;

/**
 * Smoke test: MetricsServiceProvider registers and boots its bindings in a
 * minimal container context without error.
 *
 * @uses \Tests\Support\FakeConfig
 * @uses \Tests\Support\FakeContainer
 */
#[CoversClass(MetricsServiceProvider::class)]
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
}
