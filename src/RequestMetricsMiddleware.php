<?php

declare(strict_types=1);

namespace EzPhp\Metrics;

use Closure;
use EzPhp\Contracts\MiddlewareInterface;
use EzPhp\Http\RequestInterface;
use EzPhp\Http\ResponseInterface;
use Throwable;

/**
 * Class RequestMetricsMiddleware
 *
 * Opt-in HTTP request instrumentation: an `http_requests_total` counter and an
 * `http_request_duration_seconds` histogram per request, labelled `method`,
 * `route` and `status`. Never auto-registered — add it to the global
 * middleware stack explicitly, like ez-php/otel's OtelMiddleware.
 *
 * Cardinality is bounded on purpose:
 * - `route` is the matched route *pattern* (`/users/{id}`), never the raw URI.
 *   The pattern comes from `$routeResolver`, called after `$next` returned
 *   (routing has run by then); `null` from it becomes `unmatched` (404/405).
 *   Without a resolver the label is left out entirely (method + status only).
 *   In an ez-php application:
 *   `static fn (): ?string => $app->make(Router::class)->matchedRoute()?->getPath()`.
 * - `status` is grouped (`2xx` … `5xx`) unless `$exactStatus` is true.
 * - No per-user or per-URI labels.
 *
 * An exception escaping `$next` is recorded with status 500 and rethrown.
 *
 * @package EzPhp\Metrics
 */
final readonly class RequestMetricsMiddleware implements MiddlewareInterface
{
    public const string REQUESTS_TOTAL = 'http_requests_total';

    public const string REQUEST_DURATION = 'http_request_duration_seconds';

    /**
     * @param MetricsRegistry                              $registry      Registry the two metrics are registered in.
     * @param (Closure(RequestInterface): ?string)|null    $routeResolver Matched route pattern of the request, or null when unmatched.
     * @param bool                                         $exactStatus   true = exact status code (`404`); false = class (`4xx`).
     */
    public function __construct(
        private MetricsRegistry $registry,
        private ?Closure $routeResolver = null,
        private bool $exactStatus = false,
    ) {
    }

    /**
     * @param RequestInterface $request
     * @param callable         $next
     *
     * @return ResponseInterface
     */
    public function handle(RequestInterface $request, callable $next): ResponseInterface
    {
        $start = hrtime(true);

        try {
            /** @var ResponseInterface $response */
            $response = $next($request);
        } catch (Throwable $e) {
            $this->record($request, 500, $start);

            throw $e;
        }

        $this->record($request, $response->status(), $start);

        return $response;
    }

    /**
     * @param RequestInterface $request
     * @param int              $status
     * @param int|float        $start   hrtime(true) at the start of the request.
     *
     * @return void
     */
    private function record(RequestInterface $request, int $status, int|float $start): void
    {
        $seconds = (hrtime(true) - $start) / 1e9;
        $labels = ['method' => strtoupper($request->method())];

        if ($this->routeResolver !== null) {
            $labels['route'] = ($this->routeResolver)($request) ?? 'unmatched';
        }

        $labels['status'] = $this->exactStatus ? (string) $status : intdiv($status, 100) . 'xx';

        $this->registry->counter(self::REQUESTS_TOTAL, 'Total HTTP requests')->inc($labels);
        $this->registry->histogram(self::REQUEST_DURATION, 'HTTP request duration in seconds')->observe($seconds, $labels);
    }
}
