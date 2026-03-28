<?php

declare(strict_types=1);

namespace EzPhp\Metrics;

use EzPhp\Http\Request;
use EzPhp\Http\Response;

/**
 * Handles GET /metrics — renders all registered metrics in Prometheus text format.
 *
 * HTTP 200 with content type `text/plain; version=0.0.4; charset=utf-8`.
 * Body is the full Prometheus text exposition format output.
 *
 * This controller is registered automatically by `MetricsServiceProvider::boot()`.
 * To protect the endpoint, apply authentication middleware in your route definition
 * or via global middleware — this module does not add any auth by default.
 *
 * @package EzPhp\Metrics
 */
final class MetricsController
{
    /**
     * @param MetricsRegistry $registry Injected by the container
     */
    public function __construct(private readonly MetricsRegistry $registry)
    {
    }

    /**
     * Render all metrics and return the Prometheus text response.
     */
    public function __invoke(Request $request): Response
    {
        return (new Response($this->registry->render(), 200))
            ->withHeader('Content-Type', 'text/plain; version=0.0.4; charset=utf-8');
    }
}
