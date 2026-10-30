<?php

declare(strict_types=1);

namespace EzPhp\Metrics;

use EzPhp\Contracts\JobInterface;
use EzPhp\Contracts\QueueInterface;
use Throwable;

/**
 * Class MeteredQueue
 *
 * Opt-in QueueInterface decorator: counts pushed, popped and failed jobs per
 * queue, and publishes the result of size() as a gauge. Wrap the bound queue in
 * your own provider. Cardinality is bounded: the only label is the queue name.
 *
 * The gauge only moves when size() is called (`queue:monitor`, a scheduled task),
 * because asking the driver on every push or pop would add a query per job.
 * Like ez-php/queue's UniqueQueue, the decorator hides the driver's
 * FailedJobRepositoryInterface.
 *
 * @package EzPhp\Metrics
 */
final readonly class MeteredQueue implements QueueInterface
{
    public const string PUSHED_TOTAL = 'queue_jobs_pushed_total';

    public const string POPPED_TOTAL = 'queue_jobs_popped_total';

    public const string FAILED_TOTAL = 'queue_jobs_failed_total';

    public const string SIZE = 'queue_size';

    /**
     * @param QueueInterface  $queue    The real queue driver.
     * @param MetricsRegistry $registry Registry the metrics are registered in.
     */
    public function __construct(
        private QueueInterface $queue,
        private MetricsRegistry $registry,
    ) {
    }

    /**
     * {@inheritDoc}
     */
    public function push(JobInterface $job): void
    {
        $this->queue->push($job);

        $this->registry->counter(self::PUSHED_TOTAL, 'Jobs pushed onto a queue')->inc(['queue' => $job->getQueue()]);
    }

    /**
     * {@inheritDoc}
     */
    public function pop(string $queue = 'default'): ?JobInterface
    {
        $job = $this->queue->pop($queue);

        if ($job !== null) {
            $this->registry->counter(self::POPPED_TOTAL, 'Jobs popped from a queue')->inc(['queue' => $queue]);
        }

        return $job;
    }

    /**
     * {@inheritDoc}
     */
    public function size(string $queue = 'default'): int
    {
        $size = $this->queue->size($queue);

        $this->registry->gauge(self::SIZE, 'Jobs waiting in a queue')->set((float) $size, ['queue' => $queue]);

        return $size;
    }

    /**
     * {@inheritDoc}
     */
    public function failed(JobInterface $job, Throwable $exception): void
    {
        $this->queue->failed($job, $exception);

        $this->registry->counter(self::FAILED_TOTAL, 'Jobs that failed permanently')->inc(['queue' => $job->getQueue()]);
    }
}
