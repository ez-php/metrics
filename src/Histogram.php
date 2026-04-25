<?php

declare(strict_types=1);

namespace EzPhp\Metrics;

/**
 * A histogram metric that samples observations and counts them in configurable buckets.
 *
 * Use for measuring distributions: request latency, response size, queue wait time.
 * Each call to `observe()` records a value and increments all bucket counters whose
 * upper bound is greater than or equal to the observed value (cumulative buckets).
 *
 * Labels create independent series within the same metric family:
 *
 *   $histogram->observe(0.42, ['method' => 'GET']);
 *   $histogram->observe(1.20, ['method' => 'POST']);
 *
 * The Prometheus `/metrics` output includes `_bucket{le="..."}`, `_count`, and `_sum`
 * lines for each label-set.
 *
 * @package EzPhp\Metrics
 */
final class Histogram implements MetricInterface
{
    use LabelFormatterTrait;

    /**
     * Default bucket upper bounds per the Prometheus Go client convention.
     * Suitable for measuring request latencies in seconds.
     *
     * @var list<float>
     */
    public const DEFAULT_BUCKETS = [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0];

    /**
     * Sorted bucket upper bounds (ascending).
     *
     * @var list<float>
     */
    private array $buckets;

    /**
     * Per-label-set bucket hit counts, keyed by bucket upper-bound string representation.
     *
     * @var array<string, array<string, float>>
     */
    private array $bucketCounts = [];

    /**
     * Per-label-set sum of all observed values.
     *
     * @var array<string, float>
     */
    private array $sums = [];

    /**
     * Per-label-set count of observations.
     *
     * @var array<string, int>
     */
    private array $counts = [];

    /**
     * Per-label-set label arrays, indexed by the same key as $sums/$counts.
     *
     * @var array<string, array<string, string>>
     */
    private array $labelSets = [];

    /**
     * @param string     $name    Prometheus metric name
     * @param string     $help    Human-readable description
     * @param list<float> $buckets Bucket upper bounds (excluding +Inf, which is always added)
     */
    public function __construct(
        private readonly string $name,
        private readonly string $help,
        array $buckets = self::DEFAULT_BUCKETS,
    ) {
        sort($buckets);
        $this->buckets = $buckets;
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
     * Returns MetricType::HISTOGRAM.
     */
    public function type(): MetricType
    {
        return MetricType::HISTOGRAM;
    }

    /**
     * Records one observation of `$value` for the given label-set.
     *
     * Increments all bucket counters whose upper bound is >= the observed value,
     * adds `$value` to the label-set's running sum, and increments its count.
     *
     * @param array<string, string> $labels
     */
    public function observe(float $value, array $labels = []): void
    {
        $key = $this->labelKey($labels);

        if (!isset($this->counts[$key])) {
            $this->sums[$key] = 0.0;
            $this->counts[$key] = 0;
            $this->labelSets[$key] = $labels;
            $this->bucketCounts[$key] = [];
        }

        foreach ($this->buckets as $bound) {
            if ($value <= $bound) {
                $boundStr = $this->formatValue($bound);
                $this->bucketCounts[$key][$boundStr] = ($this->bucketCounts[$key][$boundStr] ?? 0.0) + 1.0;
            }
        }

        $this->sums[$key] += $value;
        $this->counts[$key]++;
    }

    /**
     * Renders the histogram in Prometheus text exposition format.
     *
     * Emits `# HELP` and `# TYPE` lines followed by — for each label-set with
     * at least one observation — the cumulative bucket lines (including `+Inf`),
     * a `_count` line, and a `_sum` line.
     *
     * When no observations exist, only the `# HELP` and `# TYPE` lines are emitted.
     */
    public function render(): string
    {
        $output = '# HELP ' . $this->name . ' ' . $this->help . "\n";
        $output .= '# TYPE ' . $this->name . ' ' . $this->type()->value . "\n";

        foreach ($this->counts as $key => $count) {
            $labels = $this->labelSets[$key];
            $bucketCounts = $this->bucketCounts[$key];

            foreach ($this->buckets as $bound) {
                $boundStr = $this->formatValue($bound);
                $bucketCount = $bucketCounts[$boundStr] ?? 0.0;
                $bucketLabels = array_merge($labels, ['le' => $boundStr]);
                $output .= $this->name . '_bucket' . $this->renderLabels($bucketLabels)
                    . ' ' . $this->formatValue($bucketCount) . "\n";
            }

            // +Inf bucket always equals total observation count
            $infLabels = array_merge($labels, ['le' => '+Inf']);
            $output .= $this->name . '_bucket' . $this->renderLabels($infLabels) . ' ' . $count . "\n";

            $output .= $this->name . '_count' . $this->renderLabels($labels) . ' ' . $count . "\n";
            $output .= $this->name . '_sum' . $this->renderLabels($labels) . ' ' . $this->formatValue($this->sums[$key]) . "\n";
        }

        return $output;
    }
}
