<?php

declare(strict_types=1);

namespace Hyperdrive\WebSocket;

use Hyperdrive\Drivers\OpenSwooleDriver;

/**
 * Wraps a single fd on OpenSwooleDriver's native WebSocket server so gateway
 * code can talk to a connection through the WebSocketConnection interface
 * without knowing about the driver's internals.
 */
class OpenSwooleDriverConnection implements WebSocketConnection
{
    public function __construct(
        private OpenSwooleDriver $driver,
        private int $fd
    ) {}

    public function send(array $data): void
    {
        $this->driver->pushToConnection($this->fd, $data);
    }

    public function close(): void
    {
        $this->driver->closeConnection($this->fd);
    }

    public function getId(): string
    {
        return (string) $this->fd;
    }

    public function getAttributes(): array
    {
        return $this->driver->getConnectionAttributes($this->fd);
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->driver->setConnectionAttribute($this->fd, $key, $value);
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->driver->getConnectionAttribute($this->fd, $key, $default);
    }
}
