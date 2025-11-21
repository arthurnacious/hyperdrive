<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Concurrency;

use Hyperdrive\Concurrency\Concurrent;
use Hyperdrive\Concurrency\Drivers\SyncConcurrencyDriver;
use PHPUnit\Framework\TestCase;

class ConcurrentTest extends TestCase
{
    protected function setUp(): void
    {
        // Force sync mode for tests to avoid coroutine issues
        Concurrent::setDriver(new SyncConcurrencyDriver());
    }

    public function test_any_returns_first_successful_result(): void
    {
        $results = Concurrent::any([
            fn() => throw new \Exception('First failed'),
            fn() => 'second success',
            fn() => 'third success'
        ]);

        $this->assertEquals('second success', $results);
    }

    public function test_any_throws_if_all_fail(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('All operations failed');

        Concurrent::any([
            fn() => throw new \Exception('First failed'),
            fn() => throw new \Exception('Second failed'),
            fn() => throw new \Exception('Third failed')
        ]);
    }

    public function test_race_returns_first_completed_result(): void
    {
        $result = Concurrent::race([
            fn() => 'fast',
            fn() => 'slow'
        ]);

        $this->assertEquals('fast', $result);
    }

    public function test_map_processes_array(): void
    {
        $items = [1, 2, 3, 4, 5];

        $results = Concurrent::map($items, function ($item) {
            return $item * 2;
        });

        $this->assertEquals([2, 4, 6, 8, 10], $results);
    }

    public function test_map_preserves_order(): void
    {
        $items = [3, 1, 4, 2, 5];

        $results = Concurrent::map($items, function ($item) {
            return $item * 10;
        });

        $this->assertEquals([30, 10, 40, 20, 50], $results);
    }

    public function test_race_handles_exceptions(): void
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Operation failed');

        Concurrent::race([
            fn() => throw new \Exception('Operation failed'),
            fn() => 'success'
        ]);
    }
}
