<?php

declare(strict_types=1);

namespace Hyperdrive\Drivers;

use Hyperdrive\Container\Container;
use Hyperdrive\Contracts\DriverInterface;
use Hyperdrive\Http\Request;
use Hyperdrive\Http\Response;
use Hyperdrive\Routing\Router;

abstract class AbstractDriver implements DriverInterface
{
    protected bool $running = false;
    protected ?Container $container = null;
    protected ?Router $router = null;

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

    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    public function setRouter(Router $router): void
    {
        $this->router = $router;
    }

    abstract public function boot(): void;
    abstract public function listen(int $port = 3000, string $host = '0.0.0.0'): void;
    abstract public function handleRequest(Request $request): Response;
}
