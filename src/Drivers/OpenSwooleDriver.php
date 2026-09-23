<?php

declare(strict_types=1);

namespace Hyperdrive\Drivers;

use Hyperdrive\Config\Config;
use Hyperdrive\Http\Dto\Validation\ValidationException;
use Hyperdrive\Http\Middleware\MiddlewarePipeline;
use Hyperdrive\Http\Request;
use Hyperdrive\Http\Response;
use Hyperdrive\Http\StreamedResponse;
use Hyperdrive\WebSocket\OpenSwooleDriverConnection;
use Hyperdrive\WebSocket\WebSocketMessage;
use OpenSwoole\WebSocket\Server as OpenSwooleWebSocketServer;
use OpenSwoole\Http\Request as OpenSwooleRequest;
use OpenSwoole\Http\Response as OpenSwooleResponse;

class OpenSwooleDriver extends AbstractServerDriver
{
    private ?OpenSwooleWebSocketServer $server = null;

    /** @var array<int, array{gateway: array, attributes: array}> fd => connection state */
    private array $wsConnections = [];
    private ?\Hyperdrive\WebSocket\WebSocketGatewayDispatcher $webSocketDispatcher = null;

    public function boot(): void
    {
        parent::boot();

        // A registry may already have been injected via setWebSocketRegistry()
        // (Hyperdrive::boot() does this after registering the module tree, so
        // gateways declared in #[Module(gateways: [...])] are already present).
        // Fall back to an empty one for drivers booted standalone (e.g. tests).
        $this->webSocketRegistry ??= new \Hyperdrive\WebSocket\WebSocketRegistry();
        $this->webSocketDispatcher = new \Hyperdrive\WebSocket\WebSocketGatewayDispatcher($this->container);
    }

    protected function startServer(int $port = 3000, string $host = '0.0.0.0'): void
    {
        $port = $port ?? $this->getServerPort();
        $host = $host ?? $this->getServerHost();

        // Use OpenSwoole's WebSocket server class
        $this->server = new OpenSwooleWebSocketServer($host, $port);

        // Set configuration
        $serverOptions = [
            'enable_coroutine' => true,
            'open_http_protocol' => true, // Allow HTTP requests too
            'open_websocket_protocol' => true,
            // Recycle workers periodically so any slow leak in app code
            // (or ours) can't accumulate for the life of the process.
            'max_request' => Config::get('server.http.max_request', 10000),
        ];

        // Optional: hand static files under a document root straight to
        // OpenSwoole (it serves any request whose path matches a real file
        // there before 'request' ever fires), so a project can point this
        // at its public/ directory without writing a passthrough route.
        if (Config::get('server.static.enabled', false)) {
            $documentRoot = Config::get('server.static.document_root');
            if ($documentRoot && is_dir($documentRoot)) {
                $serverOptions['enable_static_handler'] = true;
                $serverOptions['document_root'] = $documentRoot;
            }
        }

        $this->server->set($serverOptions);

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

        // StreamedResponse keeps its payload in a separate resource property
        // (its own getContent() would otherwise stringify the file handle),
        // and a plain Response can also carry a raw resource as content.
        $resource = $response instanceof StreamedResponse
            ? $response->getResource()
            : $response->getRawContent();

        if (is_resource($resource)) {
            $this->streamResourceToSwoole($resource, $swooleResponse);
            return;
        }

        $swooleResponse->end($response->getContent());
    }

    /**
     * Write a resource to the Swoole response in chunks instead of buffering
     * it into memory, and guarantee the file handle is always closed.
     */
    private function streamResourceToSwoole($resource, OpenSwooleResponse $swooleResponse): void
    {
        try {
            while (!feof($resource)) {
                $chunk = fread($resource, 8192);
                if ($chunk === false || $chunk === '') {
                    break;
                }
                $swooleResponse->write($chunk);
            }
        } finally {
            fclose($resource);
        }

        $swooleResponse->end();
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

        $fd = $request->fd;
        $this->wsConnections[$fd] = [
            'gateway' => $gateway,
            'attributes' => [],
        ];

        $this->webSocketDispatcher->dispatchConnection(
            $gateway,
            new OpenSwooleDriverConnection($this, $fd)
        );

        return true;
    }

    private function handleWebSocketMessage($server, $frame): void
    {
        $fd = $frame->fd;

        if (!isset($this->wsConnections[$fd])) {
            // Not a fd we tracked at handshake time (or already closed) — ignore.
            return;
        }

        $data = json_decode($frame->data, true);
        if (!is_array($data)) {
            $data = ['type' => null, 'data' => $frame->data];
        }

        $connection = new OpenSwooleDriverConnection($this, $fd);
        $message = new WebSocketMessage($data, $connection);

        $this->webSocketDispatcher->dispatchMessage(
            $this->wsConnections[$fd]['gateway'],
            $message
        );
    }

    private function handleWebSocketClose($server, $fd): void
    {
        // The 'close' event fires for every connection (plain HTTP included),
        // so only dispatch/clean up fds we actually registered as WebSockets.
        if (!isset($this->wsConnections[$fd])) {
            return;
        }

        $gateway = $this->wsConnections[$fd]['gateway'];

        $this->webSocketDispatcher->dispatchDisconnection(
            $gateway,
            new OpenSwooleDriverConnection($this, $fd)
        );

        unset($this->wsConnections[$fd]);
    }

    public function pushToConnection(int $fd, array $data): void
    {
        if ($this->server && $this->server->exist($fd)) {
            $this->server->push($fd, json_encode($data, JSON_THROW_ON_ERROR));
        }
    }

    public function closeConnection(int $fd): void
    {
        if ($this->server && $this->server->exist($fd)) {
            $this->server->disconnect($fd);
        }

        unset($this->wsConnections[$fd]);
    }

    public function getConnectionAttributes(int $fd): array
    {
        return $this->wsConnections[$fd]['attributes'] ?? [];
    }

    public function setConnectionAttribute(int $fd, string $key, mixed $value): void
    {
        if (isset($this->wsConnections[$fd])) {
            $this->wsConnections[$fd]['attributes'][$key] = $value;
        }
    }

    public function getConnectionAttribute(int $fd, string $key, mixed $default = null): mixed
    {
        return $this->wsConnections[$fd]['attributes'][$key] ?? $default;
    }

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
