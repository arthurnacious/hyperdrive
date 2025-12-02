<?php

declare(strict_types=1);

namespace Hyperdrive\Drivers;

use Hyperdrive\Http\Dto\Validation\ValidationException;
use Hyperdrive\Http\Middleware\MiddlewarePipeline;
use Hyperdrive\Http\Request;
use Hyperdrive\Http\Response;
use OpenSwoole\WebSocket\Server as OpenSwooleWebSocketServer;
use OpenSwoole\Http\Request as OpenSwooleRequest;
use OpenSwoole\Http\Response as OpenSwooleResponse;

class OpenSwooleDriver extends AbstractServerDriver
{
    private ?OpenSwooleWebSocketServer $server = null;
    private array $webSocketServers = [];
    private ?\Hyperdrive\WebSocket\WebSocketRegistry $webSocketRegistry = null;
    private ?\Hyperdrive\WebSocket\WebSocketGatewayDispatcher $webSocketDispatcher = null;

    public function boot(): void
    {
        parent::boot();

        $this->webSocketRegistry = new \Hyperdrive\WebSocket\WebSocketRegistry();
        $this->webSocketDispatcher = new \Hyperdrive\WebSocket\WebSocketGatewayDispatcher($this->container);
    }

    protected function startServer(int $port = 3000, string $host = '0.0.0.0'): void
    {
        $port = $port ?? $this->getServerPort();
        $host = $host ?? $this->getServerHost();

        // Use OpenSwoole's WebSocket server class
        $this->server = new OpenSwooleWebSocketServer($host, $port);

        // Set configuration
        $this->server->set([
            'enable_coroutine' => true,
            'open_http_protocol' => true, // Allow HTTP requests too
            'open_websocket_protocol' => true,
        ]);

        // Register event handlers
        $this->server->on('start', function (OpenSwooleWebSocketServer $server) use ($host, $port) {
            $url = $this->getServerUrl($host, $port);
            $this->logServerStart('OpenSwoole', $url);
        });

        $this->server->on('request', function (
            OpenSwooleRequest $swooleRequest,
            OpenSwooleResponse $swooleResponse
        ) {
            $this->handleSwooleRequest($swooleRequest, $swooleResponse);
        });

        // Only register WebSocket events if WebSocket is enabled
        if ($this->isWebSocketEnabled()) {
            // Suppress deprecation warning with @
            @$this->server->on('handshake', function (
                OpenSwooleRequest $request,
                OpenSwooleResponse $response
            ) {
                return $this->handleWebSocketHandshake($request, $response);
            });

            @$this->server->on('message', function ($server, $frame) {
                $this->handleWebSocketMessage($server, $frame);
            });

            @$this->server->on('close', function ($server, $fd) {
                $this->handleWebSocketClose($server, $fd);
            });
        }

        $this->server->start();
    }

    private function handleSwooleRequest(
        OpenSwooleRequest $swooleRequest,
        OpenSwooleResponse $swooleResponse
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
            $pipeline = new MiddlewarePipeline($finalHandler);

            // Initialize and add global middlewares
            $this->initializeAndAddGlobalMiddlewares($pipeline);

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

    private function sendSwooleResponse(Response $response, OpenSwooleResponse $swooleResponse): void
    {
        $swooleResponse->status($response->getStatusCode());

        foreach ($response->getHeaders() as $name => $value) {
            $swooleResponse->header($name, $value);
        }

        $swooleResponse->end($response->getContent());
    }

    private function handleWebSocketHandshake(
        OpenSwooleRequest $request,
        OpenSwooleResponse $response
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

    private function initializeAndAddGlobalMiddlewares(MiddlewarePipeline $pipeline): void
    {
        $globalMiddlewares = \Hyperdrive\Config\Config::get('middleware.global', []);

        foreach ($globalMiddlewares as $middlewareClass) {
            if (class_exists($middlewareClass)) {
                try {
                    $middleware = $this->container->get($middlewareClass);
                    $pipeline->pipe($middleware);
                } catch (\Throwable $e) {
                    // Log error but continue
                    if ($this->environment !== 'production') {
                        error_log("Failed to initialize middleware {$middlewareClass}: " . $e->getMessage());
                    }
                }
            } elseif ($this->environment !== 'production') {
                error_log("Middleware class not found: {$middlewareClass}");
            }
        }
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
