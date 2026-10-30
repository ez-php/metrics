<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Contracts\JobInterface;
use EzPhp\Contracts\QueueInterface;
use EzPhp\Metrics\Counter;
use EzPhp\Metrics\Gauge;
use EzPhp\Metrics\MeteredQueue;
use EzPhp\Metrics\MetricsDispatcher;
use EzPhp\Metrics\MetricsRegistry;
use EzPhp\Metrics\Storage\InMemoryMetricsStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RuntimeException;
use Throwable;

/**
 * @package Tests
 */
#[CoversClass(MeteredQueue::class)]
#[UsesClass(MetricsRegistry::class)]
#[UsesClass(Counter::class)]
#[UsesClass(Gauge::class)]
#[UsesClass(MetricsDispatcher::class)]
#[UsesClass(InMemoryMetricsStorage::class)]
final class MeteredQueueTest extends TestCase
{
    private MetricsRegistry $registry;

    /** @var QueueInterface&object{pending: list<JobInterface>, failedJobs: list<JobInterface>} */
    private QueueInterface $inner;

    private MeteredQueue $queue;

    protected function setUp(): void
    {
        parent::setUp();

        $this->registry = new MetricsRegistry();
        $this->inner = new class () implements QueueInterface {
            /** @var list<JobInterface> */
            public array $pending = [];

            /** @var list<JobInterface> */
            public array $failedJobs = [];

            public function push(JobInterface $job): void
            {
                $this->pending[] = $job;
            }

            public function pop(string $queue = 'default'): ?JobInterface
            {
                return array_shift($this->pending);
            }

            public function size(string $queue = 'default'): int
            {
                return count($this->pending);
            }

            public function failed(JobInterface $job, Throwable $exception): void
            {
                $this->failedJobs[] = $job;
            }
        };
        $this->queue = new MeteredQueue($this->inner, $this->registry);
    }

    private function job(): JobInterface
    {
        return new class () implements JobInterface {
            public function handle(): void
            {
            }

            public function fail(Throwable $exception): void
            {
            }

            public function getQueue(): string
            {
                return 'mail';
            }

            public function getDelay(): int
            {
                return 0;
            }

            public function getMaxTries(): int
            {
                return 1;
            }

            public function getAttempts(): int
            {
                return 0;
            }

            public function incrementAttempts(): void
            {
            }
        };
    }

    public function test_push_delegates_and_counts_per_queue(): void
    {
        $this->queue->push($this->job());
        $this->queue->push($this->job());

        $this->assertCount(2, $this->inner->pending);
        $this->assertStringContainsString('queue_jobs_pushed_total{queue="mail"} 2', $this->registry->render());
    }

    public function test_pop_returns_the_job_and_counts_it(): void
    {
        $job = $this->job();
        $this->queue->push($job);

        $this->assertSame($job, $this->queue->pop('mail'));
        $this->assertStringContainsString('queue_jobs_popped_total{queue="mail"} 1', $this->registry->render());
    }

    public function test_an_empty_pop_counts_nothing(): void
    {
        $this->assertNull($this->queue->pop('mail'));
        $this->assertStringNotContainsString('queue_jobs_popped_total{', $this->registry->render());
    }

    public function test_failed_delegates_and_counts(): void
    {
        $job = $this->job();

        $this->queue->failed($job, new RuntimeException('boom'));

        $this->assertSame([$job], $this->inner->failedJobs);
        $this->assertStringContainsString('queue_jobs_failed_total{queue="mail"} 1', $this->registry->render());
    }

    public function test_size_returns_the_value_and_publishes_a_gauge(): void
    {
        $this->queue->push($this->job());

        $this->assertSame(1, $this->queue->size('mail'));
        $this->assertStringContainsString('queue_size{queue="mail"} 1', $this->registry->render());
    }
}
