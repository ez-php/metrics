<?php

declare(strict_types=1);

namespace EzPhp\Metrics;

/**
 * Class MetricsDispatcher
 *
 * Holds a registry's listeners and hands every MetricRecorded to them. Shared
 * by the registry and the metrics it creates. A listener that throws anyway is
 * skipped, so measuring never breaks the measured code.
 *
 * @package EzPhp\Metrics
 */
final class MetricsDispatcher
{
    /**
     * @var list<MetricsListenerInterface>
     */
    private array $listeners = [];

    /**
     * @param MetricsListenerInterface $listener
     *
     * @return void
     */
    public function add(MetricsListenerInterface $listener): void
    {
        $this->listeners[] = $listener;
    }

    /**
     * Whether anyone listens — lets metrics skip computing event values.
     */
    public function hasListeners(): bool
    {
        return $this->listeners !== [];
    }

    /**
     * @param MetricRecorded $event
     *
     * @return void
     */
    public function dispatch(MetricRecorded $event): void
    {
        foreach ($this->listeners as $listener) {
            try {
                $listener->recorded($event);
            } catch (\Throwable) {
                // An exporter failure must not reach the application.
            }
        }
    }
}
