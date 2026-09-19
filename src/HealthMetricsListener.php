<?php

declare(strict_types=1);

namespace EzPhp\Metrics;

use EzPhp\Health\HealthRegistry;
use EzPhp\Health\HealthStatus;

/**
 * Class HealthMetricsListener
 *
 * Wraps a HealthRegistry and exposes its probe results as Prometheus gauges:
 *
 *   health_probe_status{probe="database"} 1        # 1=ok, 0.5=degraded, 0=unhealthy
 *   health_probe_latency_ms{probe="database"} 1.23
 *
 * Lives in ez-php/metrics, not ez-php/health — ez-php/health's own CLAUDE.md
 * rules out metrics aggregation/Prometheus export inside that module, and
 * ez-php/metrics already exists as the natural home for exactly this.
 * ez-php/health itself is untouched.
 *
 * Requires: ez-php/health (soft dependency — require-dev only; this class
 * is only autoloaded when actually referenced).
 *
 * @package EzPhp\Metrics
 */
final class HealthMetricsListener
{
    private const string STATUS_METRIC = 'health_probe_status';

    private const string LATENCY_METRIC = 'health_probe_latency_ms';

    /**
     * @param HealthRegistry  $healthRegistry
     * @param MetricsRegistry $metricsRegistry
     */
    public function __construct(
        private readonly HealthRegistry $healthRegistry,
        private readonly MetricsRegistry $metricsRegistry,
    ) {
    }

    /**
     * Run every registered probe and record its status/latency as gauges.
     *
     * @return void
     */
    public function record(): void
    {
        $results = $this->healthRegistry->run();

        if ($results === []) {
            return;
        }

        $statusGauge = $this->metricsRegistry->gauge(
            self::STATUS_METRIC,
            'Health probe status (1=ok, 0.5=degraded, 0=unhealthy)',
        );
        $latencyGauge = $this->metricsRegistry->gauge(
            self::LATENCY_METRIC,
            'Health probe execution latency in milliseconds',
        );

        foreach ($results as $name => $result) {
            $labels = ['probe' => $name];
            $statusGauge->set($this->statusValue($result->status), $labels);
            $latencyGauge->set($result->latencyMs, $labels);
        }
    }

    /**
     * @param HealthStatus $status
     *
     * @return float
     */
    private function statusValue(HealthStatus $status): float
    {
        return match ($status) {
            HealthStatus::OK => 1.0,
            HealthStatus::DEGRADED => 0.5,
            HealthStatus::UNHEALTHY => 0.0,
        };
    }
}
