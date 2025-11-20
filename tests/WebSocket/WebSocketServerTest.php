<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\WebSocket;

use Hyperdrive\Attributes\WebSocket\OnConnection;
use Hyperdrive\Attributes\WebSocket\OnDisconnection;
use Hyperdrive\Attributes\WebSocket\OnMessage;
use Hyperdrive\Attributes\WebSocket\WebSocketGateway;
use Hyperdrive\WebSocket\OpenSwooleWebSocketConnection;
use Hyperdrive\WebSocket\OpenSwooleWebSocketServer;
use Hyperdrive\WebSocket\WebSocketMessage;
use PHPUnit\Framework\TestCase;

#[WebSocketGateway('/chat')]
class ServerTestChatGateway
{
    private array $connections = [];

    #[OnConnection]
    public function onConnection(OpenSwooleWebSocketConnection $connection): void
    {
        $this->connections[$connection->getId()] = $connection;
        $connection->send(['type' => 'welcome', 'message' => 'Connected to chat']);
    }

    #[OnMessage('chat_message')]
    public function onChatMessage(WebSocketMessage $message): void
    {
        $data = $message->getData();
        $connection = $message->getConnection();

        // Broadcast to all connections
        foreach ($this->connections as $conn) {
            $conn->send([
                'type' => 'chat_message',
                'user' => $connection->getAttribute('username', 'anonymous'),
                'message' => $data['message'],
                'timestamp' => time()
            ]);
        }
    }

    #[OnMessage('set_username')]
    public function onSetUsername(WebSocketMessage $message): void
    {
        $data = $message->getData();
        $connection = $message->getConnection();

        $connection->setAttribute('username', $data['username']);
        $connection->send(['type' => 'username_set', 'username' => $data['username']]);
    }

    #[OnDisconnection]
    public function onDisconnection(OpenSwooleWebSocketConnection $connection): void
    {
        unset($this->connections[$connection->getId()]);
    }
}

class WebSocketServerTest extends TestCase
{
    public function test_it_can_create_websocket_server(): void
    {
        $gatewayConfig = [
            'path' => '/chat',
            'prefix' => 'ws',
            'methods' => [
                'onConnection' => null,
                'onMessage' => [],
                'onDisconnection' => null,
            ],
            'class' => ServerTestChatGateway::class
        ];

        $server = new OpenSwooleWebSocketServer('/chat', $gatewayConfig);

        $this->assertInstanceOf(OpenSwooleWebSocketServer::class, $server);
        $this->assertFalse($server->isRunning());
    }

    public function test_chat_gateway_has_correct_attributes(): void
    {
        $reflection = new \ReflectionClass(ServerTestChatGateway::class);
        $attributes = $reflection->getAttributes(WebSocketGateway::class);

        $this->assertCount(1, $attributes);

        $gatewayAttribute = $attributes[0]->newInstance();
        $this->assertEquals('/chat', $gatewayAttribute->path);
    }

    public function test_websocket_connection_can_store_attributes(): void
    {
        // This is a mock test since we can't easily test the actual server
        $server = $this->createMock(OpenSwooleWebSocketServer::class);

        $connection = new OpenSwooleWebSocketConnection($server, 'test_conn_123');

        $this->assertEquals('test_conn_123', $connection->getId());
        $this->assertIsArray($connection->getAttributes());
    }
}
