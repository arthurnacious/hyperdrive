<?php

declare(strict_types=1);

namespace Hyperdrive\WebSocket;

class OpenSwooleWebSocketConnection implements WebSocketConnection
{
    public function __construct(
        private OpenSwooleWebSocketServer $server,
        private string $connectionId
    ) {}

    public function send(array $data): void
    {
        $this->server->sendToConnection($this->connectionId, $data);
    }

    public function close(): void
    {
        // Connection closure is handled by the server
    }

    public function getId(): string
    {
        return $this->connectionId;
    }

    public function getAttributes(): array
    {
        return $this->server->getConnection($this->connectionId)['attributes'] ?? [];
    }

    public function setAttribute(string $key, mixed $value): void
    {
        $this->server->setConnectionAttribute($this->connectionId, $key, $value);
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->server->getConnectionAttribute($this->connectionId, $key, $default);
    }
}
