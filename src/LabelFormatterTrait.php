<?php

declare(strict_types=1);

namespace EzPhp\Metrics;

use JsonException;

/**
 * Shared helpers for Prometheus label handling used by Counter, Gauge, and Histogram.
 *
 * @internal Not part of the public API.
 *
 * @package EzPhp\Metrics
 */
trait LabelFormatterTrait
{
    /**
     * Produces a stable string key for a set of labels.
     *
     * Labels are sorted by key before encoding so that `['b'=>'2','a'=>'1']`
     * and `['a'=>'1','b'=>'2']` map to the same series.
     *
     * @param array<string, string> $labels
     *
     * @throws JsonException
     */
    private function labelKey(array $labels): string
    {
        ksort($labels);

        return json_encode($labels, JSON_THROW_ON_ERROR);
    }

    /**
     * Renders a label set as a Prometheus label string, e.g. `{method="GET",status="200"}`.
     * Returns an empty string when no labels are present.
     *
     * @param array<string, string> $labels
     */
    private function renderLabels(array $labels): string
    {
        if ($labels === []) {
            return '';
        }

        $parts = [];

        foreach ($labels as $k => $v) {
            $parts[] = $k . '="' . addslashes($v) . '"';
        }

        return '{' . implode(',', $parts) . '}';
    }

    /**
     * Formats a float for Prometheus output.
     * Whole numbers are rendered without a decimal point (`42` not `42.0`).
     */
    private function formatValue(float $value): string
    {
        if (fmod($value, 1.0) === 0.0 && abs($value) < 1_000_000_000_000_000.0) {
            return number_format($value, 0, '.', '');
        }

        return (string) $value;
    }
}
