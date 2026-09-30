<?php

declare(strict_types=1);

namespace Tests\Storage;

use EzPhp\Metrics\Counter;
use EzPhp\Metrics\Gauge;
use EzPhp\Metrics\Histogram;
use EzPhp\Metrics\MetricsDispatcher;
use EzPhp\Metrics\MetricsRegistry;
use EzPhp\Metrics\Storage\ApcuMetricsStorage;
use EzPhp\Metrics\Storage\InMemoryMetricsStorage;
use EzPhp\Metrics\Storage\MetricDescription;
use EzPhp\Metrics\Storage\MetricsStorageInterface;
use EzPhp\Metrics\Storage\RedisMetricsStorage;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\UsesClass;
use Redis;
use Tests\TestCase;
use Throwable;

/**
 * The storage contract, run against every storage, plus the reason shared
 * storage exists: two registries (two PHP-FPM workers) adding to one series.
 *
 * Redis runs against the live service (database 5); APCu only where ext-apcu
 * is loaded with apc.enable_cli=1 — otherwise those cases are skipped.
 *
 * @package Tests\Storage
 */
#[CoversClass(InMemoryMetricsStorage::class)]
#[CoversClass(RedisMetricsStorage::class)]
#[CoversClass(ApcuMetricsStorage::class)]
#[CoversClass(MetricDescription::class)]
#[CoversClass(MetricsRegistry::class)]
#[UsesClass(Counter::class)]
#[UsesClass(Gauge::class)]
#[UsesClass(Histogram::class)]
#[UsesClass(MetricsDispatcher::class)]
final class MetricsStorageTest extends TestCase
{
    private ?MetricsStorageInterface $storage = null;

    protected function tearDown(): void
    {
        $this->storage?->wipe();
        parent::tearDown();
    }

    /**
     * @return array<string, array{string}>
     */
    public static function storages(): array
    {
        return ['memory' => ['memory'], 'redis' => ['redis'], 'apcu' => ['apcu']];
    }

    private function make(string $kind): MetricsStorageInterface
    {
        $storage = match ($kind) {
            'memory' => new InMemoryMetricsStorage(),
            'redis' => $this->redisStorage(),
            default => $this->apcuStorage(),
        };
        $storage->wipe();

        return $this->storage = $storage;
    }

    private function redisStorage(): RedisMetricsStorage
    {
        if (!extension_loaded('redis')) {
            self::markTestSkipped('ext-redis is not loaded.');
        }

        try {
            $redis = new Redis();
            $redis->connect(getenv('REDIS_HOST') ?: 'redis', (int) (getenv('REDIS_PORT') ?: 6379));
            $redis->select(5);
        } catch (Throwable $e) {
            self::markTestSkipped('Redis is not reachable: ' . $e->getMessage());
        }

        return new RedisMetricsStorage($redis, 'ez-php:metrics-test:');
    }

    private function apcuStorage(): ApcuMetricsStorage
    {
        if (!extension_loaded('apcu') || !apcu_enabled()) {
            self::markTestSkipped('ext-apcu is not loaded or apc.enable_cli is off.');
        }

        return new ApcuMetricsStorage('ez-php:metrics-test:');
    }

    #[DataProvider('storages')]
    public function test_add_set_and_fields(string $kind): void
    {
        $storage = $this->make($kind);

        $storage->add('hits', 'a', 1.0);
        $storage->add('hits', 'a', 2.5);
        $storage->add('hits', 'b', -1.0);
        $storage->set('temp', 'x', 21.5);
        $storage->set('temp', 'x', 19.25);

        $hits = $storage->fields('hits');
        ksort($hits);
        self::assertEqualsWithDelta(['a' => 3.5, 'b' => -1.0], $hits, 0.000001);
        self::assertEqualsWithDelta(['x' => 19.25], $storage->fields('temp'), 0.000001);
        self::assertSame([], $storage->fields('unknown'));
    }

    #[DataProvider('storages')]
    public function test_first_description_wins(string $kind): void
    {
        $storage = $this->make($kind);

        $storage->describe('lat', ['type' => 'histogram', 'help' => 'Latency', 'buckets' => [0.1, 1.0]]);
        $storage->describe('lat', ['type' => 'counter', 'help' => 'Other', 'buckets' => []]);

        self::assertSame(['lat' => ['type' => 'histogram', 'help' => 'Latency', 'buckets' => [0.1, 1.0]]], $storage->descriptions());
    }

    #[DataProvider('storages')]
    public function test_wipe_removes_everything(string $kind): void
    {
        $storage = $this->make($kind);
        $storage->describe('hits', ['type' => 'counter', 'help' => 'Hits', 'buckets' => []]);
        $storage->add('hits', 'a', 1.0);

        $storage->wipe();

        self::assertSame([], $storage->descriptions());
        self::assertSame([], $storage->fields('hits'));
    }

    #[DataProvider('storages')]
    public function test_two_registries_share_series_and_render_undeclared_metrics(string $kind): void
    {
        $storage = $this->make($kind);
        $workerA = new MetricsRegistry($storage);
        $workerB = new MetricsRegistry($storage);
        $metricsRequest = new MetricsRegistry($storage); // declares nothing itself

        $workerA->counter('orders_total', 'Orders')->inc(['shop' => 'eu']);
        $workerB->counter('orders_total', 'Orders')->incBy(2.0, ['shop' => 'eu']);
        $workerB->gauge('queue_depth', 'Depth')->set(7.0);
        $workerA->histogram('latency_seconds', 'Latency', [0.5, 1.0])->observe(0.3);

        $out = $metricsRequest->render();

        self::assertStringContainsString('orders_total{shop="eu"} 3', $out);
        self::assertStringContainsString('# TYPE queue_depth gauge', $out);
        self::assertStringContainsString('queue_depth 7', $out);
        self::assertStringContainsString('latency_seconds_bucket{le="0.5"} 1', $out);
        self::assertStringContainsString('latency_seconds_count 1', $out);
    }

    public function test_malformed_descriptions_are_rejected(): void
    {
        self::assertNull(MetricDescription::decode('nope'));
        self::assertNull(MetricDescription::decode('{"type":1,"help":"x"}'));
        self::assertSame(['type' => 'gauge', 'help' => 'h', 'buckets' => [1.0]], MetricDescription::decode('{"type":"gauge","help":"h","buckets":[1,"x"]}'));
    }
}
