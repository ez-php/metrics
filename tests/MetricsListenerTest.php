<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Metrics\Counter;
use EzPhp\Metrics\Gauge;
use EzPhp\Metrics\Histogram;
use EzPhp\Metrics\MetricRecorded;
use EzPhp\Metrics\MetricsDispatcher;
use EzPhp\Metrics\MetricsListenerInterface;
use EzPhp\Metrics\MetricsRegistry;
use EzPhp\Metrics\MetricType;
use EzPhp\Metrics\Storage\InMemoryMetricsStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;

/**
 * Records every event it is given.
 */
final class MetricsRecordingListener implements MetricsListenerInterface
{
    /** @var list<MetricRecorded> */
    public array $events = [];

    public function recorded(MetricRecorded $event): void
    {
        $this->events[] = $event;
    }
}

/**
 * Class MetricsListenerTest
 *
 * @package Tests
 */
#[CoversClass(MetricsRegistry::class)]
#[CoversClass(MetricsDispatcher::class)]
#[CoversClass(MetricRecorded::class)]
#[CoversClass(Counter::class)]
#[CoversClass(Gauge::class)]
#[CoversClass(Histogram::class)]
#[UsesClass(InMemoryMetricsStorage::class)]
final class MetricsListenerTest extends TestCase
{
    public function test_listeners_receive_every_recorded_value(): void
    {
        $registry = new MetricsRegistry();
        $listener = new MetricsRecordingListener();
        $registry->listen($listener);

        $registry->counter('jobs_total', 'Jobs')->incBy(2.0, ['queue' => 'mail']);
        $gauge = $registry->gauge('workers', 'Workers');
        $gauge->set(5.0);
        $gauge->inc();
        $gauge->decBy(2.0);
        $registry->histogram('latency_seconds', 'Latency')->observe(0.25, ['route' => '/']);

        $summary = array_map(
            static fn (MetricRecorded $e): array => [$e->type, $e->name, $e->value, $e->labels],
            $listener->events,
        );

        self::assertSame([
            [MetricType::COUNTER, 'jobs_total', 2.0, ['queue' => 'mail']],
            [MetricType::GAUGE, 'workers', 5.0, []],
            [MetricType::GAUGE, 'workers', 6.0, []],
            [MetricType::GAUGE, 'workers', 4.0, []],
            [MetricType::HISTOGRAM, 'latency_seconds', 0.25, ['route' => '/']],
        ], $summary);
    }

    public function test_a_throwing_listener_does_not_break_recording(): void
    {
        $registry = new MetricsRegistry();
        $registry->listen(new class () implements MetricsListenerInterface {
            public function recorded(MetricRecorded $event): void
            {
                throw new \RuntimeException('exporter down');
            }
        });
        $after = new MetricsRecordingListener();
        $registry->listen($after);

        $registry->counter('hits_total', 'Hits')->inc();

        self::assertCount(1, $after->events);
        self::assertStringContainsString('hits_total 1', $registry->render());
    }

    public function test_metrics_without_a_registry_have_no_listeners(): void
    {
        $dispatcher = new MetricsDispatcher();

        self::assertFalse($dispatcher->hasListeners());
        $counter = new Counter('c', 'C'); // default dispatcher: nothing to call
        $counter->inc();
        self::assertStringContainsString('c 1', $counter->render());
    }
}
