<?php

declare(strict_types=1);

namespace EzPhp\Metrics;

use RuntimeException;

/**
 * Base exception for the ez-php/metrics module.
 *
 * Thrown when the registry detects a type conflict (e.g. a counter and a gauge
 * registered under the same name) or when the Metrics facade is used before
 * initialisation.
 *
 * @package EzPhp\Metrics
 */
final class MetricsException extends RuntimeException
{
}
