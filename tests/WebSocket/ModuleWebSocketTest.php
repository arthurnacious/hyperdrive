<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\WebSocket;

use Hyperdrive\Application\Module;
use Hyperdrive\Application\ModuleRegistry;
use Hyperdrive\Attributes\WebSocket\OnMessage;
use Hyperdrive\Attributes\WebSocket\WebSocketGateway;
use Hyperdrive\WebSocket\WebSocketMessage;
use Hyperdrive\WebSocket\WebSocketRegistry;
use PHPUnit\Framework\TestCase;

#[WebSocketGateway('/notifications')]
class NotificationGateway
{
    #[OnMessage('notification')]
    public function onNotification(WebSocketMessage $message): void
    {
        // Handle notifications
    }
}

#[Module(
    gateways: [NotificationGateway::class],
    injectables: [SomeService::class]
)]
class NotificationModule {}

class SomeService {}

class ModuleWebSocketTest extends TestCase
{
    public function test_module_can_register_websocket_gateways(): void
    {
        $moduleRegistry = new ModuleRegistry();
        $websocketRegistry = new WebSocketRegistry();

        $moduleRegistry->register(NotificationModule::class);
        $gateways = $moduleRegistry->getGateways(NotificationModule::class);

        $this->assertEquals([NotificationGateway::class], $gateways);

        // Register the gateway with WebSocket registry
        foreach ($gateways as $gatewayClass) {
            $websocketRegistry->registerGateway($gatewayClass);
        }

        $gateway = $websocketRegistry->getGateway(NotificationGateway::class);
        $this->assertNotNull($gateway);
        $this->assertEquals('/notifications', $gateway['path']);
    }
}
