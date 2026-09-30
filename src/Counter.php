<?php

declare(strict_types=1);

namespace EzPhp\Metrics;

use EzPhp\Metrics\Storage\InMemoryMetricsStorage;
use EzPhp\Metrics\Storage\MetricsStorageInterface;
use InvalidArgumentException;

/**
 * A monotonically increasing counter metric.
 *
 * Counters can only go up. Use `inc()` or `incBy()` to record events.
 * Typical use-cases: total request count, total errors, total bytes sent.
 *
 * Labels create independent series within the same metric family:
 *
 *   $counter->inc(['method' => 'GET', 'status' => '200']);
 *   $counter->inc(['method' => 'POST', 'status' => '422']);
 *
 * @package EzPhp\Metrics
 */
final class Counter implements MetricInterface
{
    use LabelFormatterTrait;

    /**
     * @param string $name Prometheus metric name
     * @param string $help Human-readable description
     */
    public function __construct(
        private readonly string $name,
        private readonly string $help,
        private readonly MetricsStorageInterface $storage = new InMemoryMetricsStorage(),
        private readonly MetricsDispatcher $dispatcher = new MetricsDispatcher(),
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
     * Returns MetricType::COUNTER.
     */
    public function type(): MetricType
    {
        return MetricType::COUNTER;
    }

    /**
     * Increments the counter for the given label-set by 1.
     *
     * @param array<string, string> $labels
     */
    public function inc(array $labels = []): void
    {
        $this->incBy(1.0, $labels);
    }

    /**
     * Increments the counter for the given label-set by `$amount`.
     *
     * @param array<string, string> $labels
     *
     * @throws InvalidArgumentException when `$amount` is negative
     */
    public function incBy(float $amount, array $labels = []): void
    {
        if ($amount < 0.0) {
            throw new InvalidArgumentException(
                'Counter::incBy() requires a non-negative amount; counters must not decrease.'
            );
        }

        $this->storage->add($this->name, $this->labelKey($labels), $amount);

        if ($this->dispatcher->hasListeners()) {
            $this->dispatcher->dispatch(new MetricRecorded(MetricType::COUNTER, $this->name, $amount, $labels));
        }
    }

    /**
     * Renders the counter in Prometheus text exposition format.
     *
     * Emits `# HELP` and `# TYPE` lines followed by one value line per
     * label-set that has been observed. When no observations exist a single
     * zero-value line with no labels is emitted.
     */
    public function render(): string
    {
        $output = '# HELP ' . $this->name . ' ' . $this->help . "\n";
        $output .= '# TYPE ' . $this->name . ' ' . $this->type()->value . "\n";

        $values = $this->storage->fields($this->name);

        if ($values === []) {
            $output .= $this->name . ' 0' . "\n";

            return $output;
        }

        foreach ($values as $key => $value) {
            $output .= $this->name . $this->renderLabels($this->labelsFromKey($key)) . ' ' . $this->formatValue($value) . "\n";
        }

        return $output;
    }
}
