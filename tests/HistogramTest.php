<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Metrics\Histogram;
use EzPhp\Metrics\MetricType;

/**
 * @covers \EzPhp\Metrics\Histogram
 * @covers \EzPhp\Metrics\LabelFormatterTrait
 */
final class HistogramTest extends TestCase
{
    public function testNameAndHelp(): void
    {
        $histogram = new Histogram('request_duration_seconds', 'Request latency');

        self::assertSame('request_duration_seconds', $histogram->name());
        self::assertSame('Request latency', $histogram->help());
        self::assertSame(MetricType::HISTOGRAM, $histogram->type());
    }

    public function testObserveSingleValue(): void
    {
        $histogram = new Histogram('latency', 'Latency', [0.1, 0.5, 1.0]);
        $histogram->observe(0.3);

        $output = $histogram->render();

        // 0.3 <= 0.5 and 0.3 <= 1.0 but NOT 0.3 <= 0.1
        self::assertStringContainsString('latency_bucket{le="0.1"} 0', $output);
        self::assertStringContainsString('latency_bucket{le="0.5"} 1', $output);
        self::assertStringContainsString('latency_bucket{le="1"} 1', $output);
        self::assertStringContainsString('latency_bucket{le="+Inf"} 1', $output);
        self::assertStringContainsString('latency_count 1', $output);
        self::assertStringContainsString('latency_sum 0.3', $output);
    }

    public function testObserveMultipleValues(): void
    {
        $histogram = new Histogram('h', 'h', [1.0, 5.0]);
        $histogram->observe(0.5);
        $histogram->observe(2.0);
        $histogram->observe(6.0);

        $output = $histogram->render();

        // 0.5 <= 1.0 and 0.5 <= 5.0 → bucket[1.0]++, bucket[5.0]++
        // 2.0 > 1.0, 2.0 <= 5.0 → bucket[5.0]++
        // 6.0 > 5.0 → neither finite bucket
        self::assertStringContainsString('h_bucket{le="1"} 1', $output);
        self::assertStringContainsString('h_bucket{le="5"} 2', $output);
        self::assertStringContainsString('h_bucket{le="+Inf"} 3', $output);
        self::assertStringContainsString('h_count 3', $output);
        self::assertStringContainsString('h_sum 8.5', $output);
    }

    public function testCustomBuckets(): void
    {
        $histogram = new Histogram('size', 'Size', [100.0, 500.0, 1000.0]);
        $histogram->observe(200.0);

        $output = $histogram->render();

        self::assertStringContainsString('size_bucket{le="100"} 0', $output);
        self::assertStringContainsString('size_bucket{le="500"} 1', $output);
        self::assertStringContainsString('size_bucket{le="1000"} 1', $output);
        self::assertStringContainsString('size_bucket{le="+Inf"} 1', $output);
    }

    public function testBucketsAreSortedAscending(): void
    {
        // Provide buckets in reverse order — they should be sorted automatically
        $histogram = new Histogram('h', 'h', [5.0, 1.0, 2.0]);
        $histogram->observe(1.5);

        $output = $histogram->render();

        // 1.5 <= 2.0 but NOT 1.5 <= 1.0
        self::assertStringContainsString('h_bucket{le="1"} 0', $output);
        self::assertStringContainsString('h_bucket{le="2"} 1', $output);
        self::assertStringContainsString('h_bucket{le="5"} 1', $output);
    }

    public function testWithLabels(): void
    {
        $histogram = new Histogram('http_duration', 'HTTP duration', [0.1, 1.0]);
        $histogram->observe(0.05, ['method' => 'GET']);
        $histogram->observe(0.5, ['method' => 'POST']);

        $output = $histogram->render();

        self::assertStringContainsString('http_duration_bucket{method="GET",le="0.1"} 1', $output);
        self::assertStringContainsString('http_duration_bucket{method="GET",le="+Inf"} 1', $output);
        self::assertStringContainsString('http_duration_count{method="GET"} 1', $output);
        self::assertStringContainsString('http_duration_bucket{method="POST",le="0.1"} 0', $output);
        self::assertStringContainsString('http_duration_bucket{method="POST",le="+Inf"} 1', $output);
        self::assertStringContainsString('http_duration_count{method="POST"} 1', $output);
    }

    public function testRenderIncludesHelpAndTypeLine(): void
    {
        $histogram = new Histogram('dur', 'Duration');
        $histogram->observe(1.0);

        $output = $histogram->render();

        self::assertStringContainsString('# HELP dur Duration', $output);
        self::assertStringContainsString('# TYPE dur histogram', $output);
    }

    public function testRenderWithNoObservationsEmitsOnlyHelpAndType(): void
    {
        $histogram = new Histogram('empty', 'Empty');

        $output = $histogram->render();

        self::assertStringContainsString('# HELP empty Empty', $output);
        self::assertStringContainsString('# TYPE empty histogram', $output);
        self::assertStringNotContainsString('_bucket', $output);
        self::assertStringNotContainsString('_count', $output);
    }

    public function testDefaultBucketsConstantIsUsed(): void
    {
        $histogram = new Histogram('h', 'h');

        self::assertSame(Histogram::DEFAULT_BUCKETS, [0.005, 0.01, 0.025, 0.05, 0.1, 0.25, 0.5, 1.0, 2.5, 5.0, 10.0]);

        $histogram->observe(0.1);

        // Should contain the standard buckets
        self::assertStringContainsString('h_bucket{le="0.005"}', $histogram->render());
        self::assertStringContainsString('h_bucket{le="10"}', $histogram->render());
    }

    public function testRenderEndsWithNewline(): void
    {
        $histogram = new Histogram('h', 'h', [1.0]);
        $histogram->observe(0.5);

        self::assertStringEndsWith("\n", $histogram->render());
    }
}
