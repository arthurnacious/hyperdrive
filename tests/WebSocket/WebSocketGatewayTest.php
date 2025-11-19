<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\WebSocket;

use Hyperdrive\Attributes\WebSocket\OnConnection;
use Hyperdrive\Attributes\WebSocket\OnDisconnection;
use Hyperdrive\Attributes\WebSocket\OnMessage;
use Hyperdrive\Attributes\WebSocket\WebSocketGateway;
use Hyperdrive\WebSocket\WebSocketConnection;
use Hyperdrive\WebSocket\WebSocketMessage;
use PHPUnit\Framework\TestCase;

#[WebSocketGateway('/chat')]
class TestChatGateway
{
    private array $connections = [];

    #[OnConnection]
    public function onConnection(WebSocketConnection $connection): void
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
                'user' => $connection->getAttribute('user_id', 'anonymous'),
                'message' => $data['message']
            ]);
        }
    }

    #[OnDisconnection]
    public function onDisconnection(WebSocketConnection $connection): void
    {
        unset($this->connections[$connection->getId()]);
    }
}

class WebSocketGatewayTest extends TestCase
{
    public function test_it_can_create_websocket_gateway_with_attributes(): void
    {
        $reflection = new \ReflectionClass(TestChatGateway::class);
        $attributes = $reflection->getAttributes(WebSocketGateway::class);

        $this->assertCount(1, $attributes);

        $gatewayAttribute = $attributes[0]->newInstance();
        $this->assertEquals('/chat', $gatewayAttribute->path);
        $this->assertEquals('ws', $gatewayAttribute->prefix);
    }

    public function test_gateway_methods_have_websocket_attributes(): void
    {
        $reflection = new \ReflectionClass(TestChatGateway::class);

        $onConnection = $reflection->getMethod('onConnection');
        $this->assertCount(1, $onConnection->getAttributes(OnConnection::class));

        $onMessage = $reflection->getMethod('onChatMessage');
        $this->assertCount(1, $onMessage->getAttributes(OnMessage::class));

        $onDisconnection = $reflection->getMethod('onDisconnection');
        $this->assertCount(1, $onDisconnection->getAttributes(OnDisconnection::class));
    }
}
