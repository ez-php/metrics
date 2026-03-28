<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Metrics\Counter;
use EzPhp\Metrics\MetricType;
use InvalidArgumentException;

/**
 * @covers \EzPhp\Metrics\Counter
 * @covers \EzPhp\Metrics\LabelFormatterTrait
 */
final class CounterTest extends TestCase
{
    public function testNameAndHelp(): void
    {
        $counter = new Counter('requests_total', 'Total requests');

        self::assertSame('requests_total', $counter->name());
        self::assertSame('Total requests', $counter->help());
        self::assertSame(MetricType::COUNTER, $counter->type());
    }

    public function testIncByOneWithoutLabels(): void
    {
        $counter = new Counter('hits', 'Hit count');
        $counter->inc();
        $counter->inc();

        $output = $counter->render();

        self::assertStringContainsString('hits 2', $output);
    }

    public function testIncByAmount(): void
    {
        $counter = new Counter('bytes_total', 'Bytes');
        $counter->incBy(100.5);

        self::assertStringContainsString('bytes_total 100.5', $counter->render());
    }

    public function testIncByNegativeThrows(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $counter = new Counter('c', 'c');
        $counter->incBy(-1.0);
    }

    public function testWithLabels(): void
    {
        $counter = new Counter('http_requests_total', 'HTTP requests');
        $counter->inc(['method' => 'GET', 'status' => '200']);
        $counter->inc(['method' => 'POST', 'status' => '201']);
        $counter->inc(['method' => 'GET', 'status' => '200']);

        $output = $counter->render();

        self::assertStringContainsString('http_requests_total{method="GET",status="200"} 2', $output);
        self::assertStringContainsString('http_requests_total{method="POST",status="201"} 1', $output);
    }

    public function testLabelOrderIsNormalized(): void
    {
        $counter = new Counter('c', 'c');
        $counter->inc(['b' => '2', 'a' => '1']);
        $counter->inc(['a' => '1', 'b' => '2']);

        $output = $counter->render();

        // Both increments hit the same series (sorted label key)
        self::assertStringContainsString('c{a="1",b="2"} 2', $output);
    }

    public function testRenderIncludesHelpAndTypeLine(): void
    {
        $counter = new Counter('events_total', 'Event count');
        $counter->inc();

        $output = $counter->render();

        self::assertStringContainsString('# HELP events_total Event count', $output);
        self::assertStringContainsString('# TYPE events_total counter', $output);
    }

    public function testRenderEmitsZeroWhenNoObservations(): void
    {
        $counter = new Counter('empty_total', 'Empty');

        $output = $counter->render();

        self::assertStringContainsString('# HELP empty_total Empty', $output);
        self::assertStringContainsString('# TYPE empty_total counter', $output);
        self::assertStringContainsString('empty_total 0', $output);
    }

    public function testRenderEndsWithNewline(): void
    {
        $counter = new Counter('x', 'x');
        $counter->inc();

        self::assertStringEndsWith("\n", $counter->render());
    }
}
