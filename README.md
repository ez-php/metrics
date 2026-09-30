# ez-php/metrics

Prometheus metrics endpoint for the ez-php framework.

Exposes a `/metrics` route in the [Prometheus text exposition format](https://prometheus.io/docs/instrumenting/exposition_formats/).
Supports the three standard Prometheus metric types: **Counter**, **Gauge**, and **Histogram**.

---

## Installation

```bash
composer require ez-php/metrics
```

Register the provider in `provider/modules.php`:

```php
\EzPhp\Metrics\MetricsServiceProvider::class,
```

---

## Usage

### Counter — monotonically increasing

```php
use EzPhp\Metrics\Metrics;

Metrics::counter('http_requests_total', 'Total HTTP requests')
    ->inc(['method' => 'GET', 'status' => '200']);

Metrics::counter('bytes_sent_total', 'Total bytes sent')
    ->incBy(1024.0);
```

### Gauge — current value (can increase or decrease)

```php
Metrics::gauge('memory_usage_bytes', 'Current memory usage')
    ->set((float) memory_get_usage());

Metrics::gauge('active_connections', 'Active connections')
    ->inc();

Metrics::gauge('queue_depth', 'Queue depth')
    ->dec(['queue' => 'default']);
```

### Histogram — distributions and latency

```php
$start = microtime(true);
// ... handle request ...
Metrics::histogram('request_duration_seconds', 'Request duration in seconds')
    ->observe(microtime(true) - $start, ['route' => '/api/users']);
```

Custom bucket boundaries:

```php
Metrics::histogram('response_size_bytes', 'Response size', [100, 1000, 10000, 100000])
    ->observe((float) strlen($responseBody));
```

---

## /metrics endpoint

`MetricsServiceProvider` registers `GET /metrics` automatically. The response body is the full Prometheus text exposition format output:

```
# HELP http_requests_total Total HTTP requests
# TYPE http_requests_total counter
http_requests_total{method="GET",status="200"} 42

# HELP request_duration_seconds Request duration in seconds
# TYPE request_duration_seconds histogram
request_duration_seconds_bucket{route="/api/users",le="0.005"} 0
...
request_duration_seconds_bucket{route="/api/users",le="+Inf"} 5
request_duration_seconds_count{route="/api/users"} 5
request_duration_seconds_sum{route="/api/users"} 1.23
```

**Content-Type:** `text/plain; version=0.0.4; charset=utf-8`

---

## Sharing values between workers

By default values live in the PHP process — fine for long-running processes, but under PHP-FPM
every request starts from zero. Pick a shared storage in `config/metrics.php`:

| `metrics.storage` | Shared by | Requires |
|---|---|---|
| `memory` (default) | nothing — one process | — |
| `apcu` | the PHP-FPM workers of one host | `ext-apcu` (`apc.enable_cli=1` for CLI) |
| `redis` | every host | `ext-redis`, `metrics.redis.*` |

Metric descriptions are stored too, so the `/metrics` request lists series that were only touched in
other requests. APCu stores values with 6 decimal places.

## Security

The endpoint is unprotected by default, and the provider registers the route itself, so there is
no route definition of yours to add middleware to. To protect it, turn off auto-registration in
`config/metrics.php` and register the controller yourself:

```php
// config/metrics.php
return [
    'endpoint' => false, // or METRICS_ENDPOINT= (empty)
];

// routes/web.php
use EzPhp\Metrics\MetricsController;

$router->get('/metrics', [MetricsController::class, '__invoke'])
    ->middleware(App\Middleware\MetricsAuthMiddleware::class);
```

`metrics.endpoint` also changes the path (default `/metrics`). Global middleware
(`$app->middleware(...)`) works too, but applies to every route.

---

## Relation to ez-php/health

| Module | Purpose |
|---|---|
| `ez-php/health` | Liveness check — is the service up? |
| `ez-php/metrics` | Time-series data — counters, gauges, histograms for alerting and dashboards |

Both are complementary production-observability tools.

`HealthMetricsListener` bridges the two directly, so a health check's status/latency shows up
as a metric without duplicating probe logic (requires `ez-php/health` — a soft dependency,
declared in `require-dev` here, install it separately):

```php
use EzPhp\Metrics\HealthMetricsListener;

$listener = new HealthMetricsListener($healthRegistry, $metricsRegistry);
$listener->record(); // before serving /metrics, or on a schedule — your call
```

Sets `health_probe_status{probe="<name>"}` (`1`=ok, `0.5`=degraded, `0`=unhealthy) and
`health_probe_latency_ms{probe="<name>"}` for every probe in `$healthRegistry`.

---

## License

MIT
