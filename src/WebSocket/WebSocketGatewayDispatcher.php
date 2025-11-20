<?php

declare(strict_types=1);

namespace Hyperdrive\WebSocket;

use Hyperdrive\Attributes\WebSocket\OnConnection;
use Hyperdrive\Attributes\WebSocket\OnDisconnection;
use Hyperdrive\Attributes\WebSocket\OnMessage;
use Hyperdrive\Container\Container;

class WebSocketGatewayDispatcher
{
    public function __construct(
        private Container $container
    ) {}

    public function dispatchConnection(array $gateway, WebSocketConnection $connection): void
    {
        if (!$gateway['methods']['onConnection']) {
            return;
        }

        $gatewayInstance = $this->container->get($gateway['class']);
        $methodName = $gateway['methods']['onConnection'];

        $gatewayInstance->$methodName($connection);
    }

    public function dispatchMessage(array $gateway, WebSocketMessage $message): void
    {
        $gatewayInstance = $this->container->get($gateway['class']);

        foreach ($gateway['methods']['onMessage'] as $messageHandler) {
            // Check if this handler matches the message type
            if ($messageHandler['type'] === null || $messageHandler['type'] === $message->getType()) {
                $methodName = $messageHandler['method'];
                $gatewayInstance->$methodName($message);
                break; // Only call one handler per message
            }
        }
    }

    public function dispatchDisconnection(array $gateway, WebSocketConnection $connection): void
    {
        if (!$gateway['methods']['onDisconnection']) {
            return;
        }

        $gatewayInstance = $this->container->get($gateway['class']);
        $methodName = $gateway['methods']['onDisconnection'];

        $gatewayInstance->$methodName($connection);
    }
}
