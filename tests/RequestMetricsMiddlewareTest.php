<?php

declare(strict_types=1);

namespace Tests;

use EzPhp\Http\Request;
use EzPhp\Http\RequestInterface;
use EzPhp\Http\Response;
use EzPhp\Metrics\Counter;
use EzPhp\Metrics\Histogram;
use EzPhp\Metrics\MetricsDispatcher;
use EzPhp\Metrics\MetricsRegistry;
use EzPhp\Metrics\RequestMetricsMiddleware;
use EzPhp\Metrics\Storage\InMemoryMetricsStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\UsesClass;
use RuntimeException;

/**
 * @package Tests
 */
#[CoversClass(RequestMetricsMiddleware::class)]
#[UsesClass(MetricsRegistry::class)]
#[UsesClass(Counter::class)]
#[UsesClass(Histogram::class)]
#[UsesClass(MetricsDispatcher::class)]
#[UsesClass(InMemoryMetricsStorage::class)]
final class RequestMetricsMiddlewareTest extends TestCase
{
    public function test_counts_and_times_with_route_pattern_and_grouped_status(): void
    {
        $registry = new MetricsRegistry();
        $middleware = new RequestMetricsMiddleware($registry, static fn (RequestInterface $r): string => '/users/{id}');

        $response = $middleware->handle(new Request('GET', '/users/42'), static fn (): Response => new Response('ok', 201));

        $this->assertSame(201, $response->status());
        $output = $registry->render();
        $this->assertStringContainsString('http_requests_total{method="GET",route="/users/{id}",status="2xx"} 1', $output);
        $this->assertStringContainsString('http_request_duration_seconds_count{method="GET",route="/users/{id}",status="2xx"} 1', $output);
        $this->assertStringNotContainsString('/users/42', $output, 'the raw URI must never become a label');
    }

    public function test_exact_status_flag(): void
    {
        $registry = new MetricsRegistry();
        $middleware = new RequestMetricsMiddleware($registry, static fn (): string => '/x', exactStatus: true);

        $middleware->handle(new Request('post', '/x'), static fn (): Response => new Response('', 404));

        $this->assertStringContainsString('http_requests_total{method="POST",route="/x",status="404"} 1', $registry->render());
    }

    public function test_unmatched_route_label_when_resolver_returns_null(): void
    {
        $registry = new MetricsRegistry();
        $middleware = new RequestMetricsMiddleware($registry, static fn (): ?string => null);

        $middleware->handle(new Request('GET', '/missing'), static fn (): Response => new Response('', 404));

        $this->assertStringContainsString('http_requests_total{method="GET",route="unmatched",status="4xx"} 1', $registry->render());
    }

    public function test_without_resolver_the_route_label_is_omitted(): void
    {
        $registry = new MetricsRegistry();

        (new RequestMetricsMiddleware($registry))->handle(new Request('GET', '/a'), static fn (): Response => new Response(''));

        $this->assertStringContainsString('http_requests_total{method="GET",status="2xx"} 1', $registry->render());
    }

    public function test_exception_is_recorded_as_500_and_rethrown(): void
    {
        $registry = new MetricsRegistry();
        $middleware = new RequestMetricsMiddleware($registry);

        try {
            $middleware->handle(new Request('GET', '/boom'), static fn (): Response => throw new RuntimeException('boom'));
            $this->fail('exception expected');
        } catch (RuntimeException $e) {
            $this->assertSame('boom', $e->getMessage());
        }

        $this->assertStringContainsString('http_requests_total{method="GET",status="5xx"} 1', $registry->render());
    }
}
