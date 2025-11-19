<?php

declare(strict_types=1);

namespace Hyperdrive\Drivers;

use Hyperdrive\Contracts\DriverInterface;
use Hyperdrive\Http\Request;
use Hyperdrive\Http\Response;

abstract class AbstractDriver implements DriverInterface
{
    protected bool $running = false;

    public function __construct(
        protected string $rootModule,
        protected string $environment
    ) {}

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function stop(): void
    {
        $this->running = false;
    }

    abstract public function boot(): void;
    abstract public function listen(int $port = 3000, string $host = '0.0.0.0'): void;
    abstract public function handleRequest(Request $request): Response;
}
