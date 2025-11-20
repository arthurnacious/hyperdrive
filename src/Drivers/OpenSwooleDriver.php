<?php

declare(strict_types=1);

namespace Hyperdrive\Drivers;

use Hyperdrive\Http\Request;
use Hyperdrive\Http\Response;
use Hyperdrive\WebSocket\OpenSwooleWebSocketServer;
use Hyperdrive\WebSocket\WebSocketGatewayDispatcher;
use Hyperdrive\WebSocket\WebSocketRegistry;
use OpenSwoole\Http\Server as OpenSwooleServer;

class OpenSwooleDriver extends AbstractServerDriver
{
    private ?OpenSwooleServer $server = null;
    private array $webSocketServers = [];
    private ?WebSocketRegistry $webSocketRegistry = null;
    private ?WebSocketGatewayDispatcher $webSocketDispatcher = null;

    public function boot(): void
    {
        parent::boot(); // Calls parent boot for router/container initialization

        // Initialize WebSocket components
        $this->webSocketRegistry = new WebSocketRegistry();
        $this->webSocketDispatcher = new WebSocketGatewayDispatcher($this->container);
    }

    protected function startServer(int $port = 3000, string $host = '0.0.0.0'): void
    {
        $port = $port ?? $this->getServerPort();
        $host = $host ?? $this->getServerHost();

        $this->server = new OpenSwooleServer($host, $port);

        $this->server->on('start', function () use ($host, $port) {
            $url = $this->getServerUrl($host, $port);
            $this->logServerStart('OpenSwoole', $url);

            // Start WebSocket servers if enabled
            if ($this->isWebSocketEnabled()) {
                $this->startWebSocketServers();
            }
        });

        $this->server->on('request', function (
            \OpenSwoole\Http\Request $swooleRequest,
            \OpenSwoole\Http\Response $swooleResponse
        ) {
            $this->handleSwooleRequest($swooleRequest, $swooleResponse);
        });

        // WebSocket handshake and upgrade
        $this->server->on('handshake', function (
            \OpenSwoole\Http\Request $request,
            \OpenSwoole\Http\Response $response
        ) {
            return $this->handleWebSocketHandshake($request, $response);
        });

        // WebSocket frame handling
        $this->server->on('message', function (
            OpenSwooleWebSocketServer $server,
            \OpenSwoole\WebSocket\Frame $frame
        ) {
            $this->handleWebSocketMessage($server, $frame);
        });

        $this->server->on('close', function (OpenSwooleServer $server, int $fd) {
            $this->handleWebSocketClose($server, $fd);
        });

        $this->server->start();
    }

    private function handleWebSocketHandshake(
        \OpenSwoole\Http\Request $request,
        \OpenSwoole\Http\Response $response
    ): bool {
        $path = $request->server['request_uri'] ?? '/';

        // Find WebSocket gateway for this path
        $gateway = $this->webSocketRegistry->getGatewayByPath($path);
        if (!$gateway) {
            return false; // No WebSocket gateway for this path
        }

        // WebSocket handshake
        $secWebSocketKey = $request->header['sec-websocket-key'] ?? '';
        $patten = '#^[+/0-9A-Za-z]{21}[AQgw]==$#';

        if (preg_match($patten, $secWebSocketKey) === 0 || strlen(base64_decode($secWebSocketKey)) !== 16) {
            $response->end();
            return false;
        }

        $key = base64_encode(sha1(
            $secWebSocketKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11',
            true
        ));

        $response->header('Upgrade', 'websocket');
        $response->header('Connection', 'Upgrade');
        $response->header('Sec-WebSocket-Accept', $key);
        $response->header('Sec-WebSocket-Version', '13');

        $response->status(101);
        $response->end();

        return true;
    }

    private function handleWebSocketMessage(
        OpenSwooleWebSocketServer $server,
        \OpenSwoole\WebSocket\Frame $frame
    ): void {
        // This is handled by the individual WebSocket servers
        // The message event is delegated to the appropriate server based on the connection
    }

    private function handleWebSocketClose(OpenSwooleServer $server, int $fd): void
    {
        // This is handled by the individual WebSocket servers
    }

    private function startWebSocketServers(): void
    {
        foreach ($this->webSocketRegistry->getGateways() as $gateway) {
            $server = new OpenSwooleWebSocketServer($gateway['path'], $gateway);
            $server->setDispatcher($this->webSocketDispatcher);

            // Use WebSocket-specific host/port from config
            $websocketHost = $this->getWebSocketHost();
            $websocketPort = $this->getWebSocketPort();

            // Store the server for this gateway path
            $this->webSocketServers[$gateway['path']] = $server;

            echo "🔌 WebSocket gateway registered: ws://{$websocketHost}:{$websocketPort}{$gateway['path']}\n";

            // Start the WebSocket server
            $server->start($websocketPort, $websocketHost);
        }
    }

    public function registerWebSocketGateway(string $gatewayClass): void
    {
        if ($this->webSocketRegistry) {
            $this->webSocketRegistry->registerGateway($gatewayClass);

            // If server is already running, start the WebSocket server immediately
            if ($this->running && $this->server) {
                $this->startWebSocketServerForGateway($gatewayClass);
            }
        }
    }

    private function startWebSocketServerForGateway(string $gatewayClass): void
    {
        $gateway = $this->webSocketRegistry->getGateway($gatewayClass);
        if ($gateway) {
            $server = new OpenSwooleWebSocketServer($gateway['path'], $gateway);
            $server->setDispatcher($this->webSocketDispatcher);
            $this->webSocketServers[$gateway['path']] = $server;

            echo "🔌 WebSocket gateway started: {$gateway['path']}\n";
        }
    }

    public function getWebSocketServers(): array
    {
        return $this->webSocketServers;
    }

    public function getServerPort(): int
    {
        // Default port - in real implementation, this would come from config
        return 3000;
    }

    public function getServerHost(): string
    {
        return '0.0.0.0';
    }

    public function handleRequest(Request $request): Response
    {
        // This method is for internal framework use
        // Actual HTTP requests are handled via handleSwooleRequest
        return new Response('OpenSwoole: Request handled internally', 200);
    }

    private function handleSwooleRequest(
        \OpenSwoole\Http\Request $swooleRequest,
        \OpenSwoole\Http\Response $swooleResponse
    ): void {
        // Convert Swoole request to framework request
        $request = $this->createRequestFromSwoole($swooleRequest);

        // Handle the request using our framework (inherited from AbstractServerDriver)
        $response = $this->handleFrameworkRequest($request);

        // Convert framework response to Swoole response
        $this->sendSwooleResponse($response, $swooleResponse);
    }

    private function createRequestFromSwoole(\OpenSwoole\Http\Request $swooleRequest): Request
    {
        return new Request(
            query: $swooleRequest->get ?? [],
            request: $swooleRequest->post ?? [],
            attributes: [],
            cookies: $swooleRequest->cookie ?? [],
            files: $swooleRequest->files ?? [],
            server: $swooleRequest->server ?? [],
            content: $swooleRequest->rawContent() ?: null
        );
    }

    private function sendSwooleResponse(Response $response, \OpenSwoole\Http\Response $swooleResponse): void
    {
        $swooleResponse->status($response->getStatusCode());

        foreach ($response->getHeaders() as $name => $value) {
            $swooleResponse->header($name, $value);
        }

        $swooleResponse->end($response->getContent());
    }
}
