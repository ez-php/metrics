<?php

declare(strict_types=1);

namespace EzPhp\Metrics;

/**
 * Class MetricRecorded
 *
 * One recorded value, as seen by a MetricsListenerInterface.
 *
 * `$value` is the counter increment, the gauge's resulting value (after set,
 * inc or dec), or the histogram observation.
 *
 * @package EzPhp\Metrics
 */
final readonly class MetricRecorded
{
    /**
     * @param MetricType            $type
     * @param string                $name
     * @param float                 $value
     * @param array<string, string> $labels
     */
    public function __construct(
        public MetricType $type,
        public string $name,
        public float $value,
        public array $labels = [],
    ) {
    }
}
