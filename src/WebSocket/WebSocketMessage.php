<?php

declare(strict_types=1);

namespace Hyperdrive\WebSocket;

class WebSocketMessage
{
    public function __construct(
        private array $data,
        private WebSocketConnection $connection
    ) {}

    public function getData(): array
    {
        return $this->data;
    }

    public function getConnection(): WebSocketConnection
    {
        return $this->connection;
    }

    public function getType(): ?string
    {
        return $this->data['type'] ?? null;
    }
}
