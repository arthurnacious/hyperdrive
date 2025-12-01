<?php

declare(strict_types=1);

namespace Hyperdrive\Drivers;

use Hyperdrive\Http\Dto\Validation\ValidationException;
use Hyperdrive\Http\Middleware\MiddlewarePipeline;
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
        parent::boot();

        $this->webSocketRegistry = new WebSocketRegistry();
        $this->webSocketDispatcher = new WebSocketGatewayDispatcher($this->container);
    }

    protected function startServer(int $port = 3000, string $host = '0.0.0.0'): void
    {
        $port = $port ?? $this->getServerPort();
        $host = $host ?? $this->getServerHost();

        // Create server with configuration
        $this->server = new OpenSwooleServer($host, $port);

        // Set configuration
        $this->server->set([
            'enable_coroutine' => true,
            'open_http_protocol' => true,
            'open_websocket_protocol' => $this->isWebSocketEnabled(),
        ]);

        // Register event handlers
        $this->server->on('start', function (OpenSwooleServer $server) {
            $url = $this->getServerUrl($server->host, $server->port);
            $this->logServerStart('OpenSwoole', $url);

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

        // Only register WebSocket events if WebSocket is enabled
        if ($this->isWebSocketEnabled()) {
            $this->server->on('handshake', function (
                \OpenSwoole\Http\Request $request,
                \OpenSwoole\Http\Response $response
            ) {
                return $this->handleWebSocketHandshake($request, $response);
            });

            $this->server->on('message', function ($server, $frame) {
                $this->handleWebSocketMessage($server, $frame);
            });

            $this->server->on('close', function ($server, $fd) {
                $this->handleWebSocketClose($server, $fd);
            });
        }

        $this->server->start();
    }

    private function handleSwooleRequest(
        \OpenSwoole\Http\Request $swooleRequest,
        \OpenSwoole\Http\Response $swooleResponse
    ): void {
        try {
            if (!$this->router) {
                throw new \RuntimeException('Router not initialized');
            }

            $request = Request::createFromSwoole($swooleRequest);

            $route = $this->router->findRoute(
                $request->getMethod(),
                $request->getPath()
            );

            if (!$route) {
                $swooleResponse->status(404);
                $swooleResponse->end('Not Found');
                return;
            }

            $finalHandler = new \Hyperdrive\Http\Middleware\ControllerRequestHandler(
                $this->container,
                $this->dispatcher,
                $route
            );
            $pipeline = new \Hyperdrive\Http\Middleware\MiddlewarePipeline($finalHandler);

            $this->pipeGlobalMiddleware($pipeline);

            $response = $pipeline->handle($request);

            $this->sendSwooleResponse($response, $swooleResponse);
        } catch (ValidationException $e) {
            if ($this->environment !== 'production') {
                echo "✅ Validation failed (expected):\n";
                echo "📋 Errors: " . json_encode($e->getErrors(), JSON_PRETTY_PRINT) . "\n";
            }

            $swooleResponse->status(422);
            $swooleResponse->header('Content-Type', 'application/json');
            $swooleResponse->end(json_encode([
                'error' => 'Validation failed',
                'errors' => $e->getErrors()
            ]));
        } catch (\Throwable $e) {
            if ($this->environment !== 'production') {
                error_log("💥 HYPERDRIVE ERROR:");
                error_log("📍 Message: " . $e->getMessage());
                error_log("📁 File: " . $e->getFile() . ":" . $e->getLine());
                error_log("🔍 Trace:\n" . $e->getTraceAsString());
                error_log("🎯 Request: " . $swooleRequest->server['request_method'] . " " . $swooleRequest->server['request_uri']);

                echo "💥 HYPERDRIVE ERROR:\n";
                echo "📍 Message: " . $e->getMessage() . "\n";
                echo "📁 File: " . $e->getFile() . ":" . $e->getLine() . "\n";
                echo "🔍 Trace:\n" . $e->getTraceAsString() . "\n";
                echo "🎯 Request: " . $swooleRequest->server['request_method'] . " " . $swooleRequest->server['request_uri'] . "\n\n";
            }

            $swooleResponse->status(500);
            $swooleResponse->end('Server Error: ' . ($this->environment !== 'production' ? $e->getMessage() : 'Internal error'));
        }
    }

    protected function pipeGlobalMiddleware(MiddlewarePipeline $pipeline): void
    {
        if ($this->environment !== 'production') {
            echo "📝 Note: Global middleware is not yet implemented\n";
        }
    }

    private function sendSwooleResponse(Response $response, \OpenSwoole\Http\Response $swooleResponse): void
    {
        $swooleResponse->status($response->getStatusCode());

        foreach ($response->getHeaders() as $name => $value) {
            $swooleResponse->header($name, $value);
        }

        // Handle binary content
        $content = $response->getRawContent();

        if (is_resource($content)) {
            // Stream the resource
            rewind($content);
            $swooleResponse->write(stream_get_contents($content));
            fclose($content);
        } else {
            $swooleResponse->end($response->getContent());
        }
    }

    private function handleWebSocketHandshake(
        \OpenSwoole\Http\Request $request,
        \OpenSwoole\Http\Response $response
    ): bool {
        $path = $request->server['request_uri'] ?? '/';

        $gateway = $this->webSocketRegistry->getGatewayByPath($path);
        if (!$gateway) {
            return false;
        }

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

    private function handleWebSocketMessage($server, $frame): void {}

    private function handleWebSocketClose($server, $fd): void {}

    private function startWebSocketServers(): void
    {
        foreach ($this->webSocketRegistry->getGateways() as $gateway) {
            $server = new OpenSwooleWebSocketServer($gateway['path'], $gateway);
            $server->setDispatcher($this->webSocketDispatcher);

            $websocketHost = $this->getWebSocketHost();
            $websocketPort = $this->getWebSocketPort();

            $this->webSocketServers[$gateway['path']] = $server;

            $server->start($websocketPort, $websocketHost);
        }
    }

    public function registerWebSocketGateway(string $gatewayClass): void
    {
        if ($this->webSocketRegistry) {
            $this->webSocketRegistry->registerGateway($gatewayClass);

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
        }
    }

    public function getWebSocketServers(): array
    {
        return $this->webSocketServers;
    }

    public function getServerPort(): int
    {
        return 3000;
    }

    public function getServerHost(): string
    {
        return '0.0.0.0';
    }

    public function handleRequest(Request $request): Response
    {
        return new Response('OpenSwoole: Request handled internally', 200);
    }
}
