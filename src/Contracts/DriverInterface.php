<?php

declare(strict_types=1);

namespace Hyperdrive\Contracts;

use Hyperdrive\Http\Request;
use Hyperdrive\Http\Response;

interface DriverInterface
{
    public function boot(): void;
    public function listen(int $port = 3000, string $host = '0.0.0.0'): void;
    public function handleRequest(Request $request): Response;
    public function isRunning(): bool;
    public function stop(): void;
}
