<?php

declare(strict_types=1);

namespace EzPhp\Metrics;

/**
 * String-backed enum representing the Prometheus metric type.
 *
 * Used as the `type` field on MetricInterface and in the `# TYPE` comment
 * of the Prometheus text exposition format.
 *
 * @package EzPhp\Metrics
 */
enum MetricType: string
{
    case COUNTER = 'counter';
    case GAUGE = 'gauge';
    case HISTOGRAM = 'histogram';
}
