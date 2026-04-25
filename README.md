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

## Security

The `/metrics` endpoint is unprotected by default. To restrict access, apply middleware in your route definition or via global middleware in your application.

---

## Relation to ez-php/health

| Module | Purpose |
|---|---|
| `ez-php/health` | Liveness check — is the service up? |
| `ez-php/metrics` | Time-series data — counters, gauges, histograms for alerting and dashboards |

Both are complementary production-observability tools.

---

## License

MIT
