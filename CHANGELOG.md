# Changelog

All notable changes to `ez-php/metrics` are documented here.

The format follows [Keep a Changelog](https://keepachangelog.com/en/1.0.0/).

---

## [v1.2.0] — 2026-03-28

### Added
- `Counter` — monotonically increasing metric; `inc()` and `incBy()` with optional label sets
- `Gauge` — arbitrarily up/down metric; `set()`, `inc()`, `dec()`, `incBy()`, `decBy()` with optional label sets
- `Histogram` — bucket-based distribution metric; `observe()` with configurable bucket boundaries; cumulative counts and `+Inf` bucket; per-label-set tracking
- `MetricInterface` — contract for all metric types: `name()`, `help()`, `type()`, `render(): string`
- `MetricType` — backed enum: `COUNTER`, `GAUGE`, `HISTOGRAM`; used for Prometheus `# TYPE` line rendering
- `MetricsRegistry` — factory and store for metric instances; `counter()`, `gauge()`, `histogram()` return or create named metrics; throws `MetricsException` on type conflicts; `render()` assembles the full Prometheus text output
- `Metrics` — static façade backed by a `MetricsRegistry` singleton; `Metrics::counter()`, `Metrics::gauge()`, `Metrics::histogram()`, `Metrics::render()`
- `MetricsController` — handles `GET /metrics`; returns Prometheus text format response with `Content-Type: text/plain; version=0.0.4`
- `MetricsServiceProvider` — binds `MetricsRegistry`, initialises the `Metrics` façade, and registers the `GET /metrics` route
- `MetricsException` for type conflicts and uninitialised façade access
