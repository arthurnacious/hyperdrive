<?php

declare(strict_types=1);

namespace Hyperdrive\Application;

use Hyperdrive\Config\Environment;
use Hyperdrive\Contracts\DriverInterface;
use Hyperdrive\Drivers\OpenSwooleDriver;
use Hyperdrive\Drivers\RoadstarDriver;
use Hyperdrive\Drivers\SwooleDriver;
use Hyperdrive\Exceptions\DriverNotFoundException;

final class Hyperdrive
{
    private DriverInterface $driver;
    private string $environment;

    private function __construct(
        private string $rootModule,
        string $driver,
        string $environment
    ) {
        $this->environment = $environment;
        $this->driver = $this->resolveDriver($driver);
    }

    public static function create(
        string $rootModule,
        string $driver = 'auto',
        string $environment = 'production'
    ): self {
        return new self($rootModule, $driver, $environment);
    }

    public function boot(): void
    {
        // Set the environment
        Environment::setTesting($this->environment === 'testing');

        // TODO: Initialize DI container, load modules, etc.
        $this->driver->boot();
    }

    public function listen(int $port = 3000, string $host = '0.0.0.0'): void
    {
        $this->driver->listen($port, $host);
    }

    public function getDriver(): DriverInterface
    {
        return $this->driver;
    }

    private function resolveDriver(string $driver): DriverInterface
    {
        if ($driver === 'auto') {
            return $this->autoDetectDriver();
        }

        return match ($driver) {
            'openswoole' => $this->createOpenSwooleDriver(),
            'swoole' => $this->createSwooleDriver(),
            'roadstar' => $this->createRoadstarDriver(),
            default => throw new DriverNotFoundException("Driver {$driver} is not supported")
        };
    }

    private function autoDetectDriver(): DriverInterface
    {
        if (extension_loaded('openswoole')) {
            return $this->createOpenSwooleDriver();
        }

        if (extension_loaded('swoole')) {
            return $this->createSwooleDriver();
        }

        return $this->createRoadstarDriver();
    }

    private function createOpenSwooleDriver(): DriverInterface
    {
        if (!extension_loaded('openswoole')) {
            throw new DriverNotFoundException('OpenSwoole extension is not installed');
        }

        return new OpenSwooleDriver($this->rootModule, $this->environment);
    }

    private function createSwooleDriver(): DriverInterface
    {
        if (!extension_loaded('swoole')) {
            throw new DriverNotFoundException('Swoole extension is not installed');
        }

        return new SwooleDriver($this->rootModule, $this->environment);
    }

    private function createRoadstarDriver(): DriverInterface
    {
        return new RoadstarDriver($this->rootModule, $this->environment);
    }
}
