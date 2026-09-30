<?php

declare(strict_types=1);

namespace EzPhp\Metrics;

/**
 * Interface MetricsListenerInterface
 *
 * Observer seam of MetricsRegistry: called for every recorded value, so an
 * exporter (e.g. ez-php/metrics-statsd) can push it elsewhere as it happens.
 * Register with MetricsRegistry::listen().
 *
 * Must not throw: a failing exporter should not fail the code being measured.
 *
 * @package EzPhp\Metrics
 */
interface MetricsListenerInterface
{
    /**
     * @param MetricRecorded $event
     *
     * @return void
     */
    public function recorded(MetricRecorded $event): void;
}
