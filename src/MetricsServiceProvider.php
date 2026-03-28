<?php

declare(strict_types=1);

namespace EzPhp\Metrics;

use EzPhp\Contracts\ContainerInterface;
use EzPhp\Contracts\ServiceProvider;
use EzPhp\Routing\Router;

/**
 * Registers the MetricsRegistry in the container, initialises the Metrics facade,
 * and registers the GET /metrics route.
 *
 * The /metrics route is only registered when the Router is available in the
 * container. In CLI or isolated test contexts where the Router is not bound
 * the route registration is silently skipped.
 *
 * @package EzPhp\Metrics
 */
final class MetricsServiceProvider extends ServiceProvider
{
    /**
     * Bind MetricsRegistry as a shared singleton in the container.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->bind(MetricsRegistry::class, function (ContainerInterface $app): MetricsRegistry {
            return new MetricsRegistry();
        });
    }

    /**
     * Initialise the Metrics static facade and register the /metrics route.
     *
     * @return void
     */
    public function boot(): void
    {
        Metrics::setRegistry($this->app->make(MetricsRegistry::class));

        try {
            $router = $this->app->make(Router::class);
            $router->get('/metrics', [MetricsController::class, '__invoke']);
        } catch (\Throwable) {
            // Router not bound (CLI or isolated test context) — route skipped.
        }
    }
}
