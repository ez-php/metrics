<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Metrics\Counter;
use EzPhp\Metrics\Gauge;
use EzPhp\Metrics\Histogram;
use EzPhp\Metrics\MetricsException;
use EzPhp\Metrics\MetricsRegistry;

/**
 * @covers \EzPhp\Metrics\MetricsRegistry
 */
final class MetricsRegistryTest extends TestCase
{
    public function testCounterCreation(): void
    {
        $registry = new MetricsRegistry();
        $counter = $registry->counter('requests_total', 'Total requests');

        self::assertInstanceOf(Counter::class, $counter);
    }

    public function testGaugeCreation(): void
    {
        $registry = new MetricsRegistry();
        $gauge = $registry->gauge('memory_bytes', 'Memory usage');

        self::assertInstanceOf(Gauge::class, $gauge);
    }

    public function testHistogramCreation(): void
    {
        $registry = new MetricsRegistry();
        $histogram = $registry->histogram('latency_seconds', 'Request latency');

        self::assertInstanceOf(Histogram::class, $histogram);
    }

    public function testCounterRegistrationIsIdempotent(): void
    {
        $registry = new MetricsRegistry();
        $a = $registry->counter('c', 'c');
        $b = $registry->counter('c', 'c');

        self::assertSame($a, $b);
    }

    public function testGaugeRegistrationIsIdempotent(): void
    {
        $registry = new MetricsRegistry();
        $a = $registry->gauge('g', 'g');
        $b = $registry->gauge('g', 'g');

        self::assertSame($a, $b);
    }

    public function testHistogramRegistrationIsIdempotent(): void
    {
        $registry = new MetricsRegistry();
        $a = $registry->histogram('h', 'h');
        $b = $registry->histogram('h', 'h');

        self::assertSame($a, $b);
    }

    public function testCounterVsGaugeConflictThrows(): void
    {
        $this->expectException(MetricsException::class);
        $this->expectExceptionMessage('already registered as counter, not gauge');

        $registry = new MetricsRegistry();
        $registry->counter('x', 'x');
        $registry->gauge('x', 'x');
    }

    public function testGaugeVsHistogramConflictThrows(): void
    {
        $this->expectException(MetricsException::class);
        $this->expectExceptionMessage('already registered as gauge, not histogram');

        $registry = new MetricsRegistry();
        $registry->gauge('x', 'x');
        $registry->histogram('x', 'x');
    }

    public function testHistogramVsCounterConflictThrows(): void
    {
        $this->expectException(MetricsException::class);
        $this->expectExceptionMessage('already registered as histogram, not counter');

        $registry = new MetricsRegistry();
        $registry->histogram('x', 'x');
        $registry->counter('x', 'x');
    }

    public function testRenderEmptyRegistryReturnsEmptyString(): void
    {
        $registry = new MetricsRegistry();

        self::assertSame('', $registry->render());
    }

    public function testRenderIncludesAllMetrics(): void
    {
        $registry = new MetricsRegistry();
        $registry->counter('hits', 'Hit count')->inc();
        $registry->gauge('temp', 'Temperature')->set(25.0);
        $registry->histogram('latency', 'Latency', [1.0])->observe(0.5);

        $output = $registry->render();

        self::assertStringContainsString('# TYPE hits counter', $output);
        self::assertStringContainsString('# TYPE temp gauge', $output);
        self::assertStringContainsString('# TYPE latency histogram', $output);
    }

    public function testRenderSeparatesMetricFamiliesWithBlankLine(): void
    {
        $registry = new MetricsRegistry();
        $registry->counter('a', 'a')->inc();
        $registry->counter('b', 'b')->inc();

        // Each render() block ends with \n; implode("\n", ...) adds blank line between them
        self::assertStringContainsString("\n\n", $registry->render());
    }

    public function testRenderHistogramCustomBuckets(): void
    {
        $registry = new MetricsRegistry();
        $registry->histogram('h', 'h', [0.1, 0.5])->observe(0.3);

        $output = $registry->render();

        self::assertStringContainsString('h_bucket{le="0.1"} 0', $output);
        self::assertStringContainsString('h_bucket{le="0.5"} 1', $output);
    }
}
