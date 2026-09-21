<?php

declare(strict_types=1);

namespace Hyperdrive\WebSocket;

use Hyperdrive\Attributes\WebSocket\OnConnection;
use Hyperdrive\Attributes\WebSocket\OnDisconnection;
use Hyperdrive\Attributes\WebSocket\OnMessage;
use Hyperdrive\Attributes\WebSocket\WebSocketGateway;

class WebSocketRegistry
{
    private array $gateways = [];

    /**
     * @param string $modulePrefix Accumulated prefix of the module the gateway
     *                              was declared in (mirrors how controller
     *                              prefixes compound across module imports).
     */
    public function registerGateway(string $gatewayClass, string $modulePrefix = ''): void
    {
        $reflection = new \ReflectionClass($gatewayClass);
        $gatewayAttribute = $this->getGatewayAttribute($reflection);

        if ($gatewayAttribute === null) {
            throw new \InvalidArgumentException("Class {$gatewayClass} is not a WebSocket gateway");
        }

        $methods = $this->resolveGatewayMethods($reflection);

        $this->gateways[$gatewayClass] = [
            'path' => $gatewayAttribute->path,
            'prefix' => $gatewayAttribute->prefix,
            'modulePrefix' => $modulePrefix,
            'methods' => $methods,
            'class' => $gatewayClass,
        ];
    }

    public function getGateway(string $gatewayClass): ?array
    {
        return $this->gateways[$gatewayClass] ?? null;
    }

    public function getGateways(): array
    {
        return $this->gateways;
    }

    public function getGatewayByPath(string $path): ?array
    {
        foreach ($this->gateways as $gateway) {
            if ($this->buildFullPath($gateway) === $path) {
                return $gateway;
            }
        }

        return null;
    }

    private function buildFullPath(array $gateway): string
    {
        $prefix = $this->buildPath($gateway['modulePrefix'] ?? '', $gateway['prefix']);

        return $this->buildPath($prefix, $gateway['path']);
    }

    private function getGatewayAttribute(\ReflectionClass $reflection): ?WebSocketGateway
    {
        $attributes = $reflection->getAttributes(WebSocketGateway::class);

        if (empty($attributes)) {
            return null;
        }

        return $attributes[0]->newInstance();
    }

    private function resolveGatewayMethods(\ReflectionClass $reflection): array
    {
        $methods = [
            'onConnection' => null,
            'onMessage' => [],
            'onDisconnection' => null,
        ];

        foreach ($reflection->getMethods() as $method) {
            // OnConnection
            $connectionAttributes = $method->getAttributes(OnConnection::class);
            if (!empty($connectionAttributes)) {
                $methods['onConnection'] = $method->getName();
            }

            // OnMessage
            $messageAttributes = $method->getAttributes(OnMessage::class);
            foreach ($messageAttributes as $attribute) {
                $messageAttribute = $attribute->newInstance();
                $methods['onMessage'][] = [
                    'method' => $method->getName(),
                    'type' => $messageAttribute->type,
                ];
            }

            // OnDisconnection
            $disconnectionAttributes = $method->getAttributes(OnDisconnection::class);
            if (!empty($disconnectionAttributes)) {
                $methods['onDisconnection'] = $method->getName();
            }
        }

        return $methods;
    }

    private function buildPath(string $prefix, string $path): string
    {
        return \Hyperdrive\Support\PathBuilder::build($prefix, $path);
    }
}
