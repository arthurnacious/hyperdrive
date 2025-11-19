<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\WebSocket;

use Hyperdrive\WebSocket\WebSocketRegistry;
use PHPUnit\Framework\TestCase;

class WebSocketRegistryTest extends TestCase
{
    public function test_it_can_register_websocket_gateway(): void
    {
        $registry = new WebSocketRegistry();
        $registry->registerGateway(TestChatGateway::class);

        $gateway = $registry->getGateway(TestChatGateway::class);

        $this->assertNotNull($gateway);
        $this->assertEquals('/chat', $gateway['path']);
        $this->assertEquals('ws', $gateway['prefix']);
        $this->assertEquals('onConnection', $gateway['methods']['onConnection']);
        $this->assertEquals('onDisconnection', $gateway['methods']['onDisconnection']);
    }

    public function test_it_can_find_gateway_by_path(): void
    {
        $registry = new WebSocketRegistry();
        $registry->registerGateway(TestChatGateway::class);

        $gateway = $registry->getGatewayByPath('/ws/chat');

        $this->assertNotNull($gateway);
        $this->assertEquals(TestChatGateway::class, $gateway['class']);
    }

    public function test_it_handles_gateway_without_required_attributes(): void
    {
        $registry = new WebSocketRegistry();

        $this->expectException(\InvalidArgumentException::class);
        $registry->registerGateway(\stdClass::class);
    }
}
