# Coding Guidelines

Applies to the entire ez-php project — framework core, all modules, and the application template.

---

## Environment

- PHP **8.5**, Composer for dependency management
- All project based commands run **inside Docker** — never directly on the host

```
docker compose exec app <command>
```

Container name: `ez-php-app`, service name: `app`.

---

## Quality Suite

Run after every change:

```
docker compose exec app composer full
```

Executes in order:
1. `phpstan analyse` — static analysis, level 9, config: `phpstan.neon`
2. `php-cs-fixer fix` — auto-fixes style (`@PSR12` + `@PHP83Migration` + strict rules)
3. `phpunit` — all tests with coverage

Individual commands when needed:
```
composer analyse   # PHPStan only
composer cs        # CS Fixer only
composer test      # PHPUnit only
```

**PHPStan:** never suppress with `@phpstan-ignore-line` — always fix the root cause.

---

## Coding Standards

- `declare(strict_types=1)` at the top of every PHP file
- Typed properties, parameters, and return values — avoid `mixed`
- PHPDoc on every class and public method
- One responsibility per class — keep classes small and focused
- Constructor injection — no service locator pattern
- No global state unless intentional and documented

**Naming:**

| Thing | Convention |
|---|---|
| Classes / Interfaces | `PascalCase` |
| Methods / variables | `camelCase` |
| Constants | `UPPER_CASE` |
| Files | Match class name exactly |

**Principles:** SOLID · KISS · DRY · YAGNI

---

## Workflow & Behavior

- Write tests **before or alongside** production code (test-first)
- Read and understand the relevant code before making any changes
- Modify the minimal number of files necessary
- Keep implementations small — if it feels big, it likely belongs in a separate module
- No hidden magic — everything must be explicit and traceable
- No large abstractions without clear necessity
- No heavy dependencies — check if PHP stdlib suffices first
- Respect module boundaries — don't reach across packages
- Keep the framework core small — what belongs in a module stays there
- Document architectural reasoning for non-obvious design decisions
- Do not change public APIs unless necessary
- Prefer composition over inheritance — no premature abstractions

---

## New Modules & CLAUDE.md Files

### 1 — Required files

Every module under `modules/<name>/` must have:

| File | Purpose |
|---|---|
| `composer.json` | package definition, deps, autoload |
| `phpstan.neon` | static analysis config, level 9 |
| `phpunit.xml` | test suite config |
| `.php-cs-fixer.php` | code style config |
| `.gitignore` | ignore `vendor/`, `.env`, cache |
| `.env.example` | environment variable defaults (copy to `.env` on first run) |
| `docker-compose.yml` | Docker Compose service definition (always `container_name: ez-php-<name>-app`) |
| `docker/app/Dockerfile` | module Docker image (`FROM au9500/php:8.5`) |
| `docker/app/container-start.sh` | container entrypoint: `composer install` → `sleep infinity` |
| `docker/app/php.ini` | PHP ini overrides (`memory_limit`, `display_errors`, `xdebug.mode`) |
| `.github/workflows/ci.yml` | standalone CI pipeline |
| `README.md` | public documentation |
| `tests/TestCase.php` | base test case for the module |
| `start.sh` | convenience script: copy `.env`, bring up Docker, wait for services, exec shell |
| `CLAUDE.md` | see section 2 below |

### 2 — CLAUDE.md structure

Every module `CLAUDE.md` must follow this exact structure:

1. **Full content of `CODING_GUIDELINES.md`, verbatim** — copy it as-is, do not summarize or shorten
2. A `---` separator
3. `# Package: ez-php/<name>` (or `# Directory: <name>` for non-package directories)
4. Module-specific section covering:
   - Source structure — file tree with one-line description per file
   - Key classes and their responsibilities
   - Design decisions and constraints
   - Testing approach and infrastructure requirements (MySQL, Redis, etc.)
   - What does **not** belong in this module

### 3 — Docker scaffold

Run from the new module root (requires `"ez-php/docker": "^1.1"` in `require-dev`):

```
vendor/bin/docker-init
```

This copies `Dockerfile`, `docker-compose.yml`, `.env.example`, `start.sh`, and `docker/` into the module, replacing `{{MODULE_NAME}}` placeholders. Existing files are never overwritten.

After scaffolding:

1. Adapt `docker-compose.yml` — add or remove services (MySQL, Redis) as needed
2. Adapt `.env.example` — fill in connection defaults matching the services above
3. Assign a unique host port for each exposed service (see table below)

**Allocated host ports:**

| Package | `DB_HOST_PORT` (MySQL) | `REDIS_PORT` |
|---|---|---|
| root (`ez-php-project`) | 3306 | 6379 |
| `ez-php/framework` | 3307 | — |
| `ez-php/orm` | 3309 | — |
| `ez-php/cache` | — | 6380 |
| `ez-php/queue` | 3310 | 6381 |
| `ez-php/rate-limiter` | — | 6382 |
| **next free** | **3311** | **6383** |

Only set a port for services the module actually uses. Modules without external services need no port config.

### 4 — Monorepo scripts

`packages.sh` at the project root is the **central package registry**. Both `push_all.sh` and `update_all.sh` source it — the package list lives in exactly one place.

When adding a new module, add `"$ROOT/modules/<name>"` to the `PACKAGES` array in `packages.sh` in **alphabetical order** among the other `modules/*` entries (before `framework`, `ez-php`, and the root entry at the end).

---

# Package: ez-php/metrics

## Source structure

```
src/
├── MetricType.php               — enum: COUNTER, GAUGE, HISTOGRAM; used for # TYPE rendering
├── MetricInterface.php          — contract: name(), help(), type(), render(): string
├── MetricsException.php         — base exception for the module (type conflicts, uninitialised facade)
├── LabelFormatterTrait.php      — shared helpers: labelKey(), renderLabels(), formatValue()
├── Counter.php                  — monotonically increasing metric; inc(), incBy()
├── Gauge.php                    — arbitrarily up/down metric; set(), inc(), dec(), incBy(), decBy()
├── Histogram.php                — bucket-based distribution; observe(); cumulative buckets
├── MetricsRegistry.php          — factory + store: counter(), gauge(), histogram(), render()
├── Metrics.php                  — static facade backed by MetricsRegistry singleton
├── MetricsController.php        — handles GET /metrics; returns Prometheus text response
└── MetricsServiceProvider.php   — binds MetricsRegistry, initialises Metrics facade, registers route

tests/
├── TestCase.php
├── CounterTest.php              — inc, incBy, negative throw, labels, render format
├── GaugeTest.php                — set, inc, dec, incBy, decBy, negative values, render format
├── HistogramTest.php            — observe, cumulative buckets, custom buckets, labels, +Inf, render
├── MetricsRegistryTest.php      — factory idempotency, type conflict throws, render assembly
├── MetricsTest.php              — facade init/reset, delegation, fail-fast throw
└── MetricsControllerTest.php    — HTTP 200, content type, body delegation to registry
```

---

## Key classes and responsibilities

### MetricType (`src/MetricType.php`)

String-backed enum: `COUNTER` (`'counter'`), `GAUGE` (`'gauge'`), `HISTOGRAM` (`'histogram'`). Used as the value for `# TYPE` comment lines and for type-conflict error messages.

---

### MetricInterface (`src/MetricInterface.php`)

Four-method contract:
- `name(): string` — Prometheus metric name
- `help(): string` — human-readable description for `# HELP`
- `type(): MetricType` — for `# TYPE`
- `render(): string` — full Prometheus block (HELP + TYPE + value lines), always ends with `\n`

---

### LabelFormatterTrait (`src/LabelFormatterTrait.php`)

`@internal` trait used by Counter, Gauge, and Histogram to avoid duplicating three private helpers:
- `labelKey(array $labels): string` — `ksort` + `json_encode` for a stable per-series key
- `renderLabels(array $labels): string` — `{k="v",...}` or `''` when no labels
- `formatValue(float $value): string` — whole numbers without decimal point, floats as-is

---

### Counter (`src/Counter.php`)

Stores per-label-set float values (`array<string, float>`) and label arrays (`array<string, array<string, string>>`). `incBy()` throws `InvalidArgumentException` for negative amounts. `render()` emits `metric_name{labels} value` lines; emits `metric_name 0` when no observations.

---

### Gauge (`src/Gauge.php`)

Same storage pattern as Counter. `set()` replaces the value; `inc()`/`dec()`/`incBy()`/`decBy()` are relative. Supports negative values. `render()` follows the same pattern as Counter.

---

### Histogram (`src/Histogram.php`)

Stores bucket counts keyed by the string representation of the bucket bound (e.g. `"0.1"`), sums, and observation counts — all per label-set. Buckets are sorted ascending at construction. `observe()` increments all buckets whose bound ≥ the observed value (cumulative semantics). `render()` emits `_bucket{...,le="bound"}`, then `_bucket{...,le="+Inf"}` (= total count), then `_count` and `_sum` per label-set. No lines when no observations.

---

### MetricsRegistry (`src/MetricsRegistry.php`)

Stores `array<string, MetricInterface>` by name. `counter()`, `gauge()`, `histogram()` are idempotent — return the existing instance when the name is already registered under the same type. Throw `MetricsException` on type conflict (fail-fast). `render()` joins each metric's `render()` block with `"\n"` (resulting in one blank line between metric families).

---

### Metrics (`src/Metrics.php`)

Static facade following the same pattern as `Health`, `Flag`, and `Notification`. Holds `private static ?MetricsRegistry $registry`. Initialised by `MetricsServiceProvider::boot()`. Throws `RuntimeException` before initialisation. `resetRegistry()` clears the singleton for test tearDown.

---

### MetricsController (`src/MetricsController.php`)

Invokable controller resolved from the container. Calls `$registry->render()` and returns `Response` with status 200 and `Content-Type: text/plain; version=0.0.4; charset=utf-8`.

---

### MetricsServiceProvider (`src/MetricsServiceProvider.php`)

`register()` binds `MetricsRegistry` as a singleton.

`boot()` does two things:
1. Calls `Metrics::setRegistry($this->app->make(MetricsRegistry::class))`.
2. Registers `GET /metrics` on the `Router` (wrapped in `try/catch` for CLI/test contexts where the Router is not bound).

---

## Design decisions and constraints

- **Depends on `ez-php/framework` (same as `ez-php/health`).** The /metrics endpoint requires the Router. This coupling is intentional — the module's primary purpose is serving an HTTP endpoint.
- **Labels are untyped `array<string, string>` passed at observation time.** There is no upfront label-name declaration. This is simpler than pre-declaring label names and sufficient for the use-cases this module targets. The sorted `labelKey()` ensures `['b'=>'2','a'=>'1']` and `['a'=>'1','b'=>'2']` map to the same series.
- **Bucket counts keyed by string representation of the bound.** Using the string form (`'0.1'`) instead of float as an array key avoids float comparison issues and makes PHPStan-safe lookups straightforward with `array<string, float>`.
- **`LabelFormatterTrait` instead of a base class.** Three metric types (Counter, Gauge, Histogram) share three private helpers. A trait avoids coupling all three to an abstract base class while keeping the logic DRY. The trait is `@internal`.
- **`Histogram::DEFAULT_BUCKETS` constant.** The default bucket set follows the Prometheus Go client convention (suitable for HTTP request latency in seconds). Consumers can override buckets per-histogram.
- **`render()` on each metric produces a self-contained block.** The registry simply joins blocks with `"\n"`. This lets metrics be tested in complete isolation without a registry.
- **No metric persistence across requests.** In-memory only. For persistent metrics (across PHP-FPM workers, across restarts), use an external store such as Redis — this is out of scope for this module.
- **No authentication on `/metrics` by default.** Following the same pattern as `ez-php/health` — operators add middleware at the application layer.

---

## Testing approach

No external infrastructure required. All tests run in-process with no I/O.

- `CounterTest` — inc, incBy, negative throw, label normalization, render format including zero-value line
- `GaugeTest` — set, inc, dec, incBy, decBy, negative values, multi-label series, render format
- `HistogramTest` — cumulative bucket semantics, custom buckets, bucket sorting, labels on buckets/_count/_sum, +Inf always equals total count, no lines when no observations
- `MetricsRegistryTest` — idempotent factory methods, type conflict exceptions (all three combinations), render assembly, blank line separation
- `MetricsTest` — fail-fast RuntimeException before init, setRegistry/resetRegistry, delegation to registry, registry replacement
- `MetricsControllerTest` — HTTP 200, content type header, body passthrough, empty body when no metrics

`Metrics::resetRegistry()` is called in `tearDown()` of `MetricsTest` to prevent static state leaking between test classes.

---

## What does not belong in this module

| Concern | Where it belongs |
|---------|-----------------|
| Persistent metrics across PHP workers/restarts | Application layer (e.g. Redis-backed exporter) |
| Authentication on /metrics | Application middleware |
| Push gateway support (Prometheus Pushgateway) | Application layer |
| StatsD / InfluxDB / OpenTelemetry export | Separate module or application layer |
| Automatic HTTP request instrumentation | Application middleware (use `Metrics::counter(...)` in your own `MetricsMiddleware`) |
| Alerting rules | Prometheus server configuration |
| Dashboard definitions | Grafana or similar |
| Health checks / liveness probe | `ez-php/health` |
