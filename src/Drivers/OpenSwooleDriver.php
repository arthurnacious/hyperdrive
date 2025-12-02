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
use OpenSwoole\WebSocket\Server as WebSocketServer;

class OpenSwooleDriver extends AbstractServerDriver
{
    private ?WebSocketServer $server = null;
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
        if ($this->isWebSocketEnabled()) {
            $this->startWebSocketServer($host, $port);
        } else {
            $this->startHttpServer($host, $port);
        }
    }

    private function startHttpServer(string $host, int $port): void
    {
        // Use OpenSwoole HTTP server for plain HTTP requests
        $this->server = new \OpenSwoole\Http\Server($host, $port);

        $this->server->set([
            'enable_coroutine' => true,
            'open_http_protocol' => true,
        ]);

        $this->server->on('start', function ($server) use ($host, $port) {
            $url = $this->getServerUrl($host, $port);
            $this->logServerStart('OpenSwoole HTTP', $url);
        });

        $this->server->on('request', function (\OpenSwoole\Http\Request $req, \OpenSwoole\Http\Response $res) {
            $this->handleSwooleRequest($req, $res);
        });

        $this->server->start();
    }

    private function startWebSocketServer(string $host, int $port): void
    {
        // Use WebSocket server instead of HTTP server + dynamic properties
        $this->server = new WebSocketServer($host, $port);

        $this->server->set([
            'enable_coroutine' => true,
            'open_websocket_protocol' => true,
        ]);

        $this->server->on('start', function ($server) use ($host, $port) {
            $url = $this->getServerUrl($host, $port);
            $this->logServerStart('OpenSwoole WebSocket', $url);
        });

        // WebSocket events
        $this->server->on('handshake', function ($request, $response) {
            return $this->handleWebSocketHandshake($request, $response);
        });

        $this->server->on('message', function ($server, $frame) {
            $this->handleWebSocketMessage($server, $frame);
        });

        $this->server->on('close', function ($server, $fd) {
            $this->handleWebSocketClose($server, $fd);
        });

        $this->server->on('request', function (\OpenSwoole\Http\Request $req, \OpenSwoole\Http\Response $res) {
            // Optional: also handle HTTP requests on WebSocket server
            $this->handleSwooleRequest($req, $res);
        });

        $this->server->start();
    }

    private function handleSwooleRequest(\OpenSwoole\Http\Request $swooleRequest, \OpenSwoole\Http\Response $swooleResponse): void
    {
        try {
            if (!$this->router) throw new \RuntimeException('Router not initialized');

            $request = Request::createFromSwoole($swooleRequest);

            $route = $this->router->findRoute($request->getMethod(), $request->getPath());
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
            $this->initializeAndAddGlobalMiddlewares($pipeline);

            $response = $pipeline->handle($request);
            $this->sendSwooleResponse($response, $swooleResponse);
        } catch (ValidationException $e) {
            $swooleResponse->status(422);
            $swooleResponse->header('Content-Type', 'application/json');
            $swooleResponse->end(json_encode([
                'error' => 'Validation failed',
                'errors' => $e->getErrors()
            ]));
        } catch (\Throwable $e) {
            $swooleResponse->status(500);
            $swooleResponse->end('Server Error: ' . $e->getMessage());
        }
    }

    private function sendSwooleResponse(Response $response, \OpenSwoole\Http\Response $swooleResponse): void
    {
        $swooleResponse->status($response->getStatusCode());
        foreach ($response->getHeaders() as $name => $value) {
            $swooleResponse->header($name, $value);
        }
        $swooleResponse->end($response->getContent());
    }

    private function handleWebSocketHandshake($request, $response): bool
    {
        $path = $request->server['request_uri'] ?? '/';
        $gateway = $this->webSocketRegistry->getGatewayByPath($path);
        if (!$gateway) return false;

        $secWebSocketKey = $request->header['sec-websocket-key'] ?? '';
        $pattern = '#^[+/0-9A-Za-z]{21}[AQgw]==$#';
        if (preg_match($pattern, $secWebSocketKey) === 0 || strlen(base64_decode($secWebSocketKey)) !== 16) {
            $response->end();
            return false;
        }

        $key = base64_encode(sha1($secWebSocketKey . '258EAFA5-E914-47DA-95CA-C5AB0DC85B11', true));
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
                    $pipeline->pipe($this->container->get($middlewareClass));
                } catch (\Throwable $e) {
                    error_log("Failed to initialize middleware {$middlewareClass}: " . $e->getMessage());
                }
            } else {
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
