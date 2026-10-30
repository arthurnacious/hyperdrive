<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Concurrency;

use Hyperdrive\Concurrency\Concurrent;
use Hyperdrive\Concurrency\Drivers\ConcurrencyDriverInterface;
use Hyperdrive\Concurrency\Drivers\OpenSwooleConcurrencyDriver;
use Hyperdrive\Concurrency\Drivers\SyncConcurrencyDriver;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConcurrentAllTest extends TestCase
{
    protected function tearDown(): void
    {
        Concurrent::setDriver(new SyncConcurrencyDriver());
    }

    public static function drivers(): array
    {
        $drivers = ['sync' => [new SyncConcurrencyDriver(), fn (int $ms) => usleep($ms * 1000)]];

        if (extension_loaded('openswoole')) {
            $drivers['openswoole'] = [new OpenSwooleConcurrencyDriver(), fn (int $ms) => \OpenSwoole\Coroutine::usleep($ms * 1000)];
        }

        return $drivers;
    }

    #[DataProvider('drivers')]
    public function test_all_returns_every_result_under_its_key(ConcurrencyDriverInterface $driver, \Closure $sleep): void
    {
        Concurrent::setDriver($driver);

        $results = Concurrent::all([
            'user' => fn () => ['id' => 1],
            'posts' => fn () => [10, 11],
            'count' => fn () => 42,
        ]);

        $this->assertSame(['user' => ['id' => 1], 'posts' => [10, 11], 'count' => 42], $results);
    }

    #[DataProvider('drivers')]
    public function test_results_keep_input_order_whatever_finishes_first(ConcurrencyDriverInterface $driver, \Closure $sleep): void
    {
        Concurrent::setDriver($driver);

        $results = Concurrent::all([
            'slow' => function () use ($sleep) { $sleep(60); return 'slow'; },
            'fast' => fn () => 'fast',
            2 => fn () => 'numeric key',
        ]);

        $this->assertSame(['slow', 'fast', 2], array_keys($results));
        $this->assertSame(['slow' => 'slow', 'fast' => 'fast', 2 => 'numeric key'], $results);
    }

    #[DataProvider('drivers')]
    public function test_a_failure_is_rethrown_as_is(ConcurrencyDriverInterface $driver, \Closure $sleep): void
    {
        Concurrent::setDriver($driver);

        $this->expectException(\DomainException::class);
        $this->expectExceptionMessage('stats unavailable');

        Concurrent::all([
            'ok' => fn () => 1,
            'broken' => fn () => throw new \DomainException('stats unavailable'),
        ]);
    }

    #[DataProvider('drivers')]
    public function test_nothing_to_run_gives_nothing_back(ConcurrencyDriverInterface $driver, \Closure $sleep): void
    {
        Concurrent::setDriver($driver);

        $this->assertSame([], Concurrent::all([]));
    }

    public function test_openswoole_runs_the_operations_at_the_same_time(): void
    {
        if (!extension_loaded('openswoole')) {
            $this->markTestSkipped('openswoole extension not loaded');
        }

        Concurrent::setDriver(new OpenSwooleConcurrencyDriver());
        $started = microtime(true);

        Concurrent::all([
            'a' => fn () => \OpenSwoole\Coroutine::usleep(200_000),
            'b' => fn () => \OpenSwoole\Coroutine::usleep(200_000),
            'c' => fn () => \OpenSwoole\Coroutine::usleep(200_000),
        ]);

        $this->assertLessThan(0.45, microtime(true) - $started, 'three 200ms operations should overlap');
    }
}
