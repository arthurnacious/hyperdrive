<?php

declare(strict_types=1);

namespace Hyperdrive\WebSocket;

use OpenSwoole\WebSocket\Server as OpenSwooleServer;
use OpenSwoole\WebSocket\Frame;

class OpenSwooleWebSocketServer implements WebSocketServer
{
    private ?OpenSwooleServer $server = null;
    private array $connections = [];
    private bool $running = false;
    private ?WebSocketGatewayDispatcher $dispatcher = null;
    private ?array $gateway = null;

    public function __construct(
        private string $path,
        private array $gatewayConfig
    ) {}

    public function setDispatcher(WebSocketGatewayDispatcher $dispatcher): void
    {
        $this->dispatcher = $dispatcher;
    }

    public function start(int $port = 3000, string $host = '0.0.0.0'): void
    {
        $port = $port ?? 3000;
        $host = $host ?? '0.0.0.0';

        $this->server = new OpenSwooleServer($host, $port);

        // $this->server->on('start', function (OpenSwooleServer $server) use ($host, $port) {
        $this->server->on('start', function () use ($host, $port) {
            $this->running = true;
            echo "🚀 WebSocket server listening on ws://{$host}:{$port}{$this->path}\n";
        });

        $this->server->on('open', function (OpenSwooleServer $server, \OpenSwoole\Http\Request $request) {
            $connectionId = $this->generateConnectionId();
            $this->connections[$connectionId] = [
                'fd' => $request->fd,
                'id' => $connectionId,
                'attributes' => []
            ];

            // Store connection ID in the server for later use
            $server->connections[$request->fd] = $connectionId;

            echo "🔗 WebSocket connection opened: {$connectionId}\n";

            // Dispatch connection event to gateway
            $this->dispatchConnection($connectionId);
        });

        $this->server->on('message', function (OpenSwooleServer $server, Frame $frame) {
            $connectionId = $server->connections[$frame->fd] ?? null;
            if (!$connectionId) {
                return;
            }

            $data = json_decode($frame->data, true) ?? ['type' => 'unknown', 'data' => $frame->data];

            echo "📨 WebSocket message from {$connectionId}: " . json_encode($data) . "\n";

            // Dispatch message event to gateway
            $this->dispatchMessage($connectionId, $data);
        });

        $this->server->on('close', function (OpenSwooleServer $server, int $fd) {
            $connectionId = $server->connections[$fd] ?? null;
            if ($connectionId && isset($this->connections[$connectionId])) {
                echo "🔒 WebSocket connection closed: {$connectionId}\n";

                // Dispatch disconnection event to gateway
                $this->dispatchDisconnection($connectionId);

                unset($this->connections[$connectionId]);
                unset($server->connections[$fd]);
            }
        });

        $this->server->start();
    }

    public function stop(): void
    {
        $this->running = false;
        if ($this->server) {
            $this->server->stop(-1);
        }
    }

    public function broadcast(array $data, ?array $connections = null): void
    {
        $targetConnections = $connections ?? $this->connections;

        foreach ($targetConnections as $connection) {
            $this->sendToConnection($connection['id'], $data);
        }
    }

    public function sendToConnection(string $connectionId, array $data): void
    {
        if (!isset($this->connections[$connectionId])) {
            return;
        }

        $fd = $this->connections[$connectionId]['fd'];
        $jsonData = json_encode($data, JSON_THROW_ON_ERROR);

        $this->server->push($fd, $jsonData);
    }

    public function isRunning(): bool
    {
        return $this->running;
    }

    public function getConnection(string $connectionId): ?array
    {
        return $this->connections[$connectionId] ?? null;
    }

    public function setConnectionAttribute(string $connectionId, string $key, mixed $value): void
    {
        if (isset($this->connections[$connectionId])) {
            $this->connections[$connectionId]['attributes'][$key] = $value;
        }
    }

    public function getConnectionAttribute(string $connectionId, string $key, mixed $default = null): mixed
    {
        return $this->connections[$connectionId]['attributes'][$key] ?? $default;
    }

    private function generateConnectionId(): string
    {
        return uniqid('conn_', true);
    }

    private function dispatchConnection(string $connectionId): void
    {
        if (!$this->dispatcher || !$this->gatewayConfig) {
            return;
        }

        $connection = new OpenSwooleWebSocketConnection($this, $connectionId);
        $this->dispatcher->dispatchConnection($this->gatewayConfig, $connection);
    }

    private function dispatchMessage(string $connectionId, array $data): void
    {
        if (!$this->dispatcher || !$this->gatewayConfig) {
            return;
        }

        $connection = new OpenSwooleWebSocketConnection($this, $connectionId);
        $message = new WebSocketMessage($data, $connection);
        $this->dispatcher->dispatchMessage($this->gatewayConfig, $message);
    }

    private function dispatchDisconnection(string $connectionId): void
    {
        if (!$this->dispatcher || !$this->gatewayConfig) {
            return;
        }

        $connection = new OpenSwooleWebSocketConnection($this, $connectionId);
        $this->dispatcher->dispatchDisconnection($this->gatewayConfig, $connection);
    }
}
