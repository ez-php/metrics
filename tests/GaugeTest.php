<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Metrics\Gauge;
use EzPhp\Metrics\MetricType;

/**
 * @covers \EzPhp\Metrics\Gauge
 * @covers \EzPhp\Metrics\LabelFormatterTrait
 */
final class GaugeTest extends TestCase
{
    public function testNameAndHelp(): void
    {
        $gauge = new Gauge('memory_bytes', 'Memory usage');

        self::assertSame('memory_bytes', $gauge->name());
        self::assertSame('Memory usage', $gauge->help());
        self::assertSame(MetricType::GAUGE, $gauge->type());
    }

    public function testSet(): void
    {
        $gauge = new Gauge('queue_depth', 'Queue depth');
        $gauge->set(42.0);

        self::assertStringContainsString('queue_depth 42', $gauge->render());
    }

    public function testSetOverwritesPreviousValue(): void
    {
        $gauge = new Gauge('g', 'g');
        $gauge->set(10.0);
        $gauge->set(20.0);

        self::assertStringContainsString('g 20', $gauge->render());
    }

    public function testSetNegativeValue(): void
    {
        $gauge = new Gauge('temp', 'Temperature');
        $gauge->set(-5.0);

        self::assertStringContainsString('temp -5', $gauge->render());
    }

    public function testInc(): void
    {
        $gauge = new Gauge('connections', 'Active connections');
        $gauge->inc();
        $gauge->inc();

        self::assertStringContainsString('connections 2', $gauge->render());
    }

    public function testDec(): void
    {
        $gauge = new Gauge('workers', 'Workers');
        $gauge->set(5.0);
        $gauge->dec();

        self::assertStringContainsString('workers 4', $gauge->render());
    }

    public function testIncBy(): void
    {
        $gauge = new Gauge('g', 'g');
        $gauge->incBy(3.5);

        self::assertStringContainsString('g 3.5', $gauge->render());
    }

    public function testDecBy(): void
    {
        $gauge = new Gauge('g', 'g');
        $gauge->set(10.0);
        $gauge->decBy(3.0);

        self::assertStringContainsString('g 7', $gauge->render());
    }

    public function testWithLabels(): void
    {
        $gauge = new Gauge('cpu_usage', 'CPU usage');
        $gauge->set(0.75, ['core' => '0']);
        $gauge->set(0.50, ['core' => '1']);

        $output = $gauge->render();

        self::assertStringContainsString('cpu_usage{core="0"} 0.75', $output);
        self::assertStringContainsString('cpu_usage{core="1"} 0.5', $output);
    }

    public function testRenderIncludesHelpAndTypeLine(): void
    {
        $gauge = new Gauge('mem', 'Memory');
        $gauge->set(1.0);

        $output = $gauge->render();

        self::assertStringContainsString('# HELP mem Memory', $output);
        self::assertStringContainsString('# TYPE mem gauge', $output);
    }

    public function testRenderEmitsZeroWhenNoObservations(): void
    {
        $gauge = new Gauge('empty', 'Empty');

        $output = $gauge->render();

        self::assertStringContainsString('# HELP empty Empty', $output);
        self::assertStringContainsString('empty 0', $output);
    }

    public function testRenderEndsWithNewline(): void
    {
        $gauge = new Gauge('g', 'g');
        $gauge->set(1.0);

        self::assertStringEndsWith("\n", $gauge->render());
    }
}
