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

#[WebSocketGateway('/status')]
class StatusGateway {}

#[Module(gateways: [StatusGateway::class], prefix: 'admin')]
class AdminModule {}

#[Module(imports: [AdminModule::class], prefix: 'api')]
class ApiRootModule {}

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

    public function test_registering_a_module_automatically_registers_its_gateways(): void
    {
        $moduleRegistry = new ModuleRegistry();
        $websocketRegistry = new WebSocketRegistry();
        $moduleRegistry->setWebSocketRegistry($websocketRegistry);

        // No manual loop over getGateways()/registerGateway() here - just
        // registering the module is enough.
        $moduleRegistry->register(NotificationModule::class);

        $gateway = $websocketRegistry->getGateway(NotificationGateway::class);
        $this->assertNotNull($gateway);
        $this->assertEquals('/notifications', $gateway['path']);
    }

    public function test_a_gateways_module_prefix_compounds_with_the_gateways_own_prefix(): void
    {
        $moduleRegistry = new ModuleRegistry();
        $websocketRegistry = new WebSocketRegistry();
        $moduleRegistry->setWebSocketRegistry($websocketRegistry);

        // ApiRootModule (prefix 'api') imports AdminModule (prefix 'admin'),
        // which declares StatusGateway (#[WebSocketGateway('/status')], the
        // default 'ws' gateway prefix). Effective path should be the same
        // compounding controllers get: /api/admin/ws/status.
        $moduleRegistry->register(ApiRootModule::class);

        $gateway = $websocketRegistry->getGatewayByPath('/api/admin/ws/status');
        $this->assertNotNull($gateway);
        $this->assertEquals(StatusGateway::class, $gateway['class']);
    }
}
