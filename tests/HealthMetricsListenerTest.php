<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Health\HealthRegistry;
use EzPhp\Health\HealthResult;
use EzPhp\Health\ProbeInterface;
use EzPhp\Metrics\HealthMetricsListener;
use EzPhp\Metrics\MetricsRegistry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Class HealthMetricsListenerTest
 *
 * @package Tests
 */
final class HealthMetricsListenerFakeProbe implements ProbeInterface
{
    public function __construct(
        private readonly string $probeName,
        private readonly HealthResult $result,
    ) {
    }

    public function name(): string
    {
        return $this->probeName;
    }

    public function check(): HealthResult
    {
        return $this->result;
    }
}

#[CoversClass(HealthMetricsListener::class)]
#[UsesClass(HealthRegistry::class)]
#[UsesClass(MetricsRegistry::class)]
final class HealthMetricsListenerTest extends TestCase
{
    public function testRecordSetsOneGaugeValuePerProbeStatus(): void
    {
        $healthRegistry = new HealthRegistry([
            new HealthMetricsListenerFakeProbe('database', HealthResult::ok('database', 'connected', 1.5)),
            new HealthMetricsListenerFakeProbe('redis', HealthResult::unhealthy('redis', 'timeout', 2.0)),
        ]);
        $metricsRegistry = new MetricsRegistry();

        $listener = new HealthMetricsListener($healthRegistry, $metricsRegistry);
        $listener->record();

        $rendered = $metricsRegistry->render();

        $this->assertStringContainsString('health_probe_status{probe="database"} 1', $rendered);
        $this->assertStringContainsString('health_probe_status{probe="redis"} 0', $rendered);
    }

    public function testRecordSetsDegradedStatusAsHalf(): void
    {
        $healthRegistry = new HealthRegistry([
            new HealthMetricsListenerFakeProbe('queue', HealthResult::degraded('queue', 'no jobs table', 0.5)),
        ]);
        $metricsRegistry = new MetricsRegistry();

        $listener = new HealthMetricsListener($healthRegistry, $metricsRegistry);
        $listener->record();

        $this->assertStringContainsString('health_probe_status{probe="queue"} 0.5', $metricsRegistry->render());
    }

    public function testRecordSetsLatencyGaugePerProbe(): void
    {
        $healthRegistry = new HealthRegistry([
            new HealthMetricsListenerFakeProbe('database', HealthResult::ok('database', 'connected', 12.34)),
        ]);
        $metricsRegistry = new MetricsRegistry();

        $listener = new HealthMetricsListener($healthRegistry, $metricsRegistry);
        $listener->record();

        $this->assertStringContainsString('health_probe_latency_ms{probe="database"} 12.34', $metricsRegistry->render());
    }

    public function testRecordWithNoProbesRendersEmptyMetrics(): void
    {
        $healthRegistry = new HealthRegistry([]);
        $metricsRegistry = new MetricsRegistry();

        $listener = new HealthMetricsListener($healthRegistry, $metricsRegistry);
        $listener->record();

        $this->assertStringNotContainsString('probe=', $metricsRegistry->render());
    }
}
