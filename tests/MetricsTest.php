<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Metrics\Counter;
use EzPhp\Metrics\Gauge;
use EzPhp\Metrics\Histogram;
use EzPhp\Metrics\Metrics;
use EzPhp\Metrics\MetricsRegistry;
use RuntimeException;

/**
 * @covers \EzPhp\Metrics\Metrics
 */
final class MetricsTest extends TestCase
{
    protected function tearDown(): void
    {
        Metrics::resetRegistry();
    }

    public function testCounterBeforeInitialisationThrows(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Metrics facade is not initialised');

        Metrics::counter('x', 'x');
    }

    public function testGaugeBeforeInitialisationThrows(): void
    {
        $this->expectException(RuntimeException::class);

        Metrics::gauge('x', 'x');
    }

    public function testHistogramBeforeInitialisationThrows(): void
    {
        $this->expectException(RuntimeException::class);

        Metrics::histogram('x', 'x');
    }

    public function testRenderBeforeInitialisationThrows(): void
    {
        $this->expectException(RuntimeException::class);

        Metrics::render();
    }

    public function testSetRegistryAndCounter(): void
    {
        Metrics::setRegistry(new MetricsRegistry());

        $counter = Metrics::counter('hits', 'Hits');

        self::assertInstanceOf(Counter::class, $counter);
    }

    public function testSetRegistryAndGauge(): void
    {
        Metrics::setRegistry(new MetricsRegistry());

        $gauge = Metrics::gauge('mem', 'Memory');

        self::assertInstanceOf(Gauge::class, $gauge);
    }

    public function testSetRegistryAndHistogram(): void
    {
        Metrics::setRegistry(new MetricsRegistry());

        $histogram = Metrics::histogram('dur', 'Duration');

        self::assertInstanceOf(Histogram::class, $histogram);
    }

    public function testRenderDelegatestoRegistry(): void
    {
        Metrics::setRegistry(new MetricsRegistry());
        Metrics::counter('c', 'c')->inc();

        $output = Metrics::render();

        self::assertStringContainsString('# TYPE c counter', $output);
    }

    public function testResetRegistryClearsState(): void
    {
        Metrics::setRegistry(new MetricsRegistry());
        Metrics::resetRegistry();

        $this->expectException(RuntimeException::class);

        Metrics::counter('x', 'x');
    }

    public function testSetRegistryReplacesPrevious(): void
    {
        $registry1 = new MetricsRegistry();
        $registry2 = new MetricsRegistry();

        Metrics::setRegistry($registry1);
        Metrics::counter('a', 'a')->inc();

        Metrics::setRegistry($registry2);

        // The new registry has no metrics
        self::assertSame('', Metrics::render());
    }

    public function testHistogramWithCustomBuckets(): void
    {
        Metrics::setRegistry(new MetricsRegistry());

        $histogram = Metrics::histogram('h', 'h', [0.1, 1.0]);
        $histogram->observe(0.5);

        $output = Metrics::render();

        self::assertStringContainsString('h_bucket{le="0.1"} 0', $output);
        self::assertStringContainsString('h_bucket{le="1"} 1', $output);
    }
}
