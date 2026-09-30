<?php

declare(strict_types=1);

namespace EzPhp\Metrics;

use EzPhp\Metrics\Storage\InMemoryMetricsStorage;
use EzPhp\Metrics\Storage\MetricsStorageInterface;

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
        $this->storage->set($this->name, $this->labelKey($labels), $value);
        $this->notify($labels);
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
        $this->storage->add($this->name, $this->labelKey($labels), $amount);
        $this->notify($labels);
    }

    /**
     * Decrements the gauge by `$amount` for the given label-set.
     *
     * @param array<string, string> $labels
     */
    public function decBy(float $amount, array $labels = []): void
    {
        $this->storage->add($this->name, $this->labelKey($labels), -$amount);
        $this->notify($labels);
    }

    /**
     * Tell listeners the gauge's resulting value for this label set.
     *
     * @param array<string, string> $labels
     */
    private function notify(array $labels): void
    {
        if (!$this->dispatcher->hasListeners()) {
            return;
        }

        $value = $this->storage->fields($this->name)[$this->labelKey($labels)] ?? 0.0;
        $this->dispatcher->dispatch(new MetricRecorded(MetricType::GAUGE, $this->name, $value, $labels));
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
