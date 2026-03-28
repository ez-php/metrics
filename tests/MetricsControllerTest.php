<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Http\Request;
use EzPhp\Metrics\MetricsController;
use EzPhp\Metrics\MetricsRegistry;

/**
 * @covers \EzPhp\Metrics\MetricsController
 */
final class MetricsControllerTest extends TestCase
{
    private function makeRequest(): Request
    {
        return new Request('GET', '/metrics');
    }

    public function testReturns200(): void
    {
        $registry = new MetricsRegistry();
        $controller = new MetricsController($registry);

        $response = ($controller)($this->makeRequest());

        self::assertSame(200, $response->status());
    }

    public function testContentTypeIsPrometheusTextFormat(): void
    {
        $registry = new MetricsRegistry();
        $controller = new MetricsController($registry);

        $response = ($controller)($this->makeRequest());

        self::assertSame(
            'text/plain; version=0.0.4; charset=utf-8',
            $response->headers()['Content-Type']
        );
    }

    public function testBodyContainsRegistryOutput(): void
    {
        $registry = new MetricsRegistry();
        $registry->counter('requests_total', 'Total requests')->inc();

        $controller = new MetricsController($registry);
        $response = ($controller)($this->makeRequest());

        self::assertStringContainsString('# TYPE requests_total counter', $response->body());
        self::assertStringContainsString('requests_total 1', $response->body());
    }

    public function testBodyIsEmptyStringWhenNoMetricsRegistered(): void
    {
        $registry = new MetricsRegistry();
        $controller = new MetricsController($registry);

        $response = ($controller)($this->makeRequest());

        self::assertSame('', $response->body());
    }

    public function testBodyContainsMultipleMetrics(): void
    {
        $registry = new MetricsRegistry();
        $registry->counter('c', 'c')->inc();
        $registry->gauge('g', 'g')->set(5.0);
        $registry->histogram('h', 'h', [1.0])->observe(0.5);

        $controller = new MetricsController($registry);
        $response = ($controller)($this->makeRequest());
        $body = $response->body();

        self::assertStringContainsString('# TYPE c counter', $body);
        self::assertStringContainsString('# TYPE g gauge', $body);
        self::assertStringContainsString('# TYPE h histogram', $body);
    }
}
