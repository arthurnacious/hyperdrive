<?php

declare(strict_types=1);

namespace Hyperdrive\Concurrency;

use Hyperdrive\Concurrency\Drivers\ConcurrencyDriverInterface;
use Hyperdrive\Concurrency\Drivers\OpenSwooleConcurrencyDriver;
use Hyperdrive\Concurrency\Drivers\SwooleConcurrencyDriver;
use Hyperdrive\Concurrency\Drivers\SyncConcurrencyDriver;

final class Concurrent
{
    private static ?ConcurrencyDriverInterface $driver = null;

    public static function any(array $operations): mixed
    {
        return self::getDriver()->any($operations);
    }

    public static function race(array $operations): mixed
    {
        return self::getDriver()->race($operations);
    }

    public static function map(array $items, callable $mapper): array
    {
        return self::getDriver()->map($items, $mapper);
    }

    public static function setDriver(ConcurrencyDriverInterface $driver): void
    {
        self::$driver = $driver;
    }

    private static function getDriver(): ConcurrencyDriverInterface
    {
        if (self::$driver === null) {
            self::$driver = self::createDriver();
        }

        return self::$driver;
    }

    private static function createDriver(): ConcurrencyDriverInterface
    {
        // Only use sync mode if we're explicitly in a test environment
        // or if no async extensions are available
        if (self::isTestEnvironment()) {
            return new SyncConcurrencyDriver();
        }

        if (extension_loaded('openswoole')) {
            return new OpenSwooleConcurrencyDriver();
        }

        if (extension_loaded('swoole')) {
            return new SwooleConcurrencyDriver();
        }

        // Fallback to synchronous implementation
        return new SyncConcurrencyDriver();
    }

    private static function isTestEnvironment(): bool
    {
        // Check for common test environment indicators
        return defined('PHPUNIT_COMPOSER_INSTALL') ||
            class_exists(\PHPUnit\Framework\TestCase::class) ||
            (isset($_SERVER['argv']) && in_array('--env=test', $_SERVER['argv']));
    }
}
