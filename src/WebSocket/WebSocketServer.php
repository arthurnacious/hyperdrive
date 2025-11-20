<?php

declare(strict_types=1);

namespace Hyperdrive\WebSocket;

interface WebSocketServer
{
    public function start(int $port = 3000, string $host = '0.0.0.0'): void;
    public function stop(): void;
    public function broadcast(array $data, ?array $connections = null): void;
    public function sendToConnection(string $connectionId, array $data): void;
    public function isRunning(): bool;
}
