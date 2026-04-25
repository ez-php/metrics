<?php

declare(strict_types=1);

namespace EzPhp\Metrics;

/**
 * A gauge metric that can arbitrarily increase or decrease.
 *
 * Use for values that represent a current state: queue depth, memory usage,
 * active connections, temperature.
 *
 * Labels create independent series within the same metric family:
 *
 *   $gauge->set(42.5, ['worker' => 'queue-1']);
 *   $gauge->inc(['worker' => 'queue-2']);
 *
 * @package EzPhp\Metrics
 */
final class Gauge implements MetricInterface
{
    use LabelFormatterTrait;

    /** @var array<string, float> */
    private array $values = [];

    /** @var array<string, array<string, string>> */
    private array $labelSets = [];

    /**
     * @param string $name Prometheus metric name
     * @param string $help Human-readable description
     */
    public function __construct(
        private readonly string $name,
        private readonly string $help,
    ) {
    }

    /**
     * Returns the metric name.
     */
    public function name(): string
    {
        return $this->name;
    }

    /**
     * Returns the help text.
     */
    public function help(): string
    {
        return $this->help;
    }

    /**
     * Returns MetricType::GAUGE.
     */
    public function type(): MetricType
    {
        return MetricType::GAUGE;
    }

    /**
     * Sets the gauge to an absolute value for the given label-set.
     *
     * @param array<string, string> $labels
     */
    public function set(float $value, array $labels = []): void
    {
        $key = $this->labelKey($labels);
        $this->values[$key] = $value;
        $this->labelSets[$key] = $labels;
    }

    /**
     * Increments the gauge by 1 for the given label-set.
     *
     * @param array<string, string> $labels
     */
    public function inc(array $labels = []): void
    {
        $this->incBy(1.0, $labels);
    }

    /**
     * Decrements the gauge by 1 for the given label-set.
     *
     * @param array<string, string> $labels
     */
    public function dec(array $labels = []): void
    {
        $this->decBy(1.0, $labels);
    }

    /**
     * Increments the gauge by `$amount` for the given label-set.
     *
     * @param array<string, string> $labels
     */
    public function incBy(float $amount, array $labels = []): void
    {
        $key = $this->labelKey($labels);
        $this->values[$key] = ($this->values[$key] ?? 0.0) + $amount;
        $this->labelSets[$key] = $labels;
    }

    /**
     * Decrements the gauge by `$amount` for the given label-set.
     *
     * @param array<string, string> $labels
     */
    public function decBy(float $amount, array $labels = []): void
    {
        $key = $this->labelKey($labels);
        $this->values[$key] = ($this->values[$key] ?? 0.0) - $amount;
        $this->labelSets[$key] = $labels;
    }

    /**
     * Renders the gauge in Prometheus text exposition format.
     *
     * Emits `# HELP` and `# TYPE` lines followed by one value line per
     * label-set that has been observed. When no observations exist a single
     * zero-value line with no labels is emitted.
     */
    public function render(): string
    {
        $output = '# HELP ' . $this->name . ' ' . $this->help . "\n";
        $output .= '# TYPE ' . $this->name . ' ' . $this->type()->value . "\n";

        if ($this->values === []) {
            $output .= $this->name . ' 0' . "\n";

            return $output;
        }

        foreach ($this->values as $key => $value) {
            $labels = $this->labelSets[$key];
            $output .= $this->name . $this->renderLabels($labels) . ' ' . $this->formatValue($value) . "\n";
        }

        return $output;
    }
}
