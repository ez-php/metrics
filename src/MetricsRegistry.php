<?php

declare(strict_types=1);

namespace EzPhp\Metrics;

use EzPhp\Metrics\Storage\InMemoryMetricsStorage;
use EzPhp\Metrics\Storage\MetricsStorageInterface;

/**
 * Central registry for all Prometheus metrics.
 *
 * Acts as a factory and store: calling `counter()`, `gauge()`, or `histogram()`
 * with a name that already exists returns the existing metric instance (idempotent
 * registration). Calling them with a name registered under a different type throws
 * a `MetricsException` (fail-fast type conflict detection).
 *
 * `render()` produces the full Prometheus text exposition format output for all
 * registered metrics, ready to be served at the `/metrics` endpoint.
 *
 * Values live in a MetricsStorageInterface — per process by default; with a shared
 * storage (APCu, Redis) every PHP-FPM worker adds to the same series. Each metric's
 * description is stored too, so render() also includes metrics that this process
 * never declared (e.g. counted only in other requests).
 *
 * @package EzPhp\Metrics
 */
final class MetricsRegistry
{
    /** @var array<string, MetricInterface> */
    private array $metrics = [];

    /**
     * @param MetricsStorageInterface $storage
     */
    private readonly MetricsDispatcher $dispatcher;

    public function __construct(
        private readonly MetricsStorageInterface $storage = new InMemoryMetricsStorage(),
    ) {
        $this->dispatcher = new MetricsDispatcher();
    }

    /**
     * Register a listener for every value recorded through this registry's metrics
     * (e.g. ez-php/metrics-statsd's StatsdMetricsListener).
     *
     * @param MetricsListenerInterface $listener
     *
     * @return void
     */
    public function listen(MetricsListenerInterface $listener): void
    {
        $this->dispatcher->add($listener);
    }

    /**
     * The storage the metrics of this registry write to.
     */
    public function storage(): MetricsStorageInterface
    {
        return $this->storage;
    }

    /**
     * Returns (or creates) a `Counter` with the given name.
     *
     * When the name is already registered as a different metric type, a
     * `MetricsException` is thrown. The `$help` text is ignored on subsequent
     * calls with the same name.
     *
     * @throws MetricsException on type conflict
     */
    public function counter(string $name, string $help): Counter
    {
        if (!isset($this->metrics[$name])) {
            $this->metrics[$name] = new Counter($name, $help, $this->storage, $this->dispatcher);
            $this->storage->describe($name, ['type' => MetricType::COUNTER->value, 'help' => $help, 'buckets' => []]);
        }

        $metric = $this->metrics[$name];

        if (!$metric instanceof Counter) {
            throw new MetricsException(
                "Metric '$name' is already registered as {$metric->type()->value}, not counter."
            );
        }

        return $metric;
    }

    /**
     * Returns (or creates) a `Gauge` with the given name.
     *
     * When the name is already registered as a different metric type, a
     * `MetricsException` is thrown. The `$help` text is ignored on subsequent
     * calls with the same name.
     *
     * @throws MetricsException on type conflict
     */
    public function gauge(string $name, string $help): Gauge
    {
        if (!isset($this->metrics[$name])) {
            $this->metrics[$name] = new Gauge($name, $help, $this->storage, $this->dispatcher);
            $this->storage->describe($name, ['type' => MetricType::GAUGE->value, 'help' => $help, 'buckets' => []]);
        }

        $metric = $this->metrics[$name];

        if (!$metric instanceof Gauge) {
            throw new MetricsException(
                "Metric '$name' is already registered as {$metric->type()->value}, not gauge."
            );
        }

        return $metric;
    }

    /**
     * Returns (or creates) a `Histogram` with the given name and bucket configuration.
     *
     * When the name is already registered as a different metric type, a
     * `MetricsException` is thrown. The `$help` and `$buckets` parameters are
     * ignored on subsequent calls with the same name.
     *
     * @param list<float> $buckets Bucket upper bounds (excluding +Inf)
     *
     * @throws MetricsException on type conflict
     */
    public function histogram(
        string $name,
        string $help,
        array $buckets = Histogram::DEFAULT_BUCKETS,
    ): Histogram {
        if (!isset($this->metrics[$name])) {
            $this->metrics[$name] = new Histogram($name, $help, $buckets, $this->storage, $this->dispatcher);
            $this->storage->describe($name, ['type' => MetricType::HISTOGRAM->value, 'help' => $help, 'buckets' => $buckets]);
        }

        $metric = $this->metrics[$name];

        if (!$metric instanceof Histogram) {
            throw new MetricsException(
                "Metric '$name' is already registered as {$metric->type()->value}, not histogram."
            );
        }

        return $metric;
    }

    /**
     * Renders all registered metrics in Prometheus text exposition format.
     *
     * Metric families are separated by a blank line. Returns an empty string
     * when no metrics have been registered.
     *
     * Content-Type: `text/plain; version=0.0.4; charset=utf-8`
     */
    public function render(): string
    {
        $metrics = $this->metrics;

        // Metrics known only from the storage (declared by other processes).
        foreach ($this->storage->descriptions() as $name => $description) {
            if (isset($metrics[$name])) {
                continue;
            }

            $metric = match ($description['type']) {
                MetricType::COUNTER->value => new Counter($name, $description['help'], $this->storage),
                MetricType::GAUGE->value => new Gauge($name, $description['help'], $this->storage),
                MetricType::HISTOGRAM->value => new Histogram(
                    $name,
                    $description['help'],
                    $description['buckets'] !== [] ? $description['buckets'] : Histogram::DEFAULT_BUCKETS,
                    $this->storage,
                ),
                default => null,
            };

            if ($metric !== null) {
                $metrics[$name] = $metric;
            }
        }

        if ($metrics === []) {
            return '';
        }

        $blocks = [];

        foreach ($metrics as $metric) {
            $blocks[] = $metric->render();
        }

        return implode("\n", $blocks);
    }
}
