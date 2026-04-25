<?php

declare(strict_types=1);

namespace EzPhp\Metrics;

/**
 * Contract for a single Prometheus metric family.
 *
 * Implementations are responsible for tracking their own state (per label-set)
 * and for rendering themselves in the Prometheus text exposition format,
 * including the `# HELP` and `# TYPE` comment lines.
 *
 * @package EzPhp\Metrics
 */
interface MetricInterface
{
    /**
     * Returns the metric name as it appears in the Prometheus output.
     * Must match `[a-zA-Z_:][a-zA-Z0-9_:]*`.
     */
    public function name(): string;

    /**
     * Returns the human-readable description shown in the `# HELP` comment.
     */
    public function help(): string;

    /**
     * Returns the Prometheus metric type for the `# TYPE` comment.
     */
    public function type(): MetricType;

    /**
     * Renders the full metric block in Prometheus text exposition format.
     *
     * Output includes:
     *   - `# HELP name help text`
     *   - `# TYPE name type`
     *   - One or more value lines (one per label-set with observations)
     *
     * Always ends with a newline character.
     */
    public function render(): string;
}
