<?php

declare(strict_types=1);

namespace Hyperdrive\Drivers;

use Hyperdrive\Config\Config;
use Hyperdrive\Container\Container;
use Hyperdrive\Http\ControllerDispatcher;
use Hyperdrive\Http\Middleware\ControllerRequestHandler;
use Hyperdrive\Http\Middleware\MiddlewareInterface;
use Hyperdrive\Http\Middleware\MiddlewarePipeline;
use Hyperdrive\Http\Request;
use Hyperdrive\Http\Response;
use Hyperdrive\Routing\Router;

abstract class AbstractServerDriver extends AbstractDriver
{
    protected string $protocol = 'http';
    protected array $serverOptions = [];
    protected ?ControllerDispatcher $dispatcher = null;

    /** @var MiddlewareInterface[]|null */
    private ?array $globalMiddlewareInstances = null;

    public function boot(): void
    {
        $this->running = true;

        // Only initialize router if not already set via setRouter()
        if (!$this->router) {
            $this->router = new Router();
        }

        // Initialize container and dispatcher
        if (!$this->container) {
            $this->container = new Container();
        }

        $this->dispatcher = new ControllerDispatcher($this->container);

        // 🆕 Pre-initialize global middleware instances
        $this->initializeGlobalMiddleware();
    }

    public function getServerHost(): string
    {
        return Config::get('server.http.host', '0.0.0.0');
    }

    public function getServerPort(): int
    {
        return Config::get('server.http.port', 3000);
    }

    public function getWebSocketHost(): string
    {
        return Config::get('server.websocket.host', $this->getServerHost());
    }

    public function getWebSocketPort(): int
    {
        return Config::get('server.websocket.port', $this->getServerPort());
    }

    public function isWebSocketEnabled(): bool
    {
        return Config::get('server.websocket.enabled', true);
    }

    public function setServerOptions(array $options): void
    {
        $this->serverOptions = $options;
        $this->detectProtocol();
    }

    public function listen(int $port = 3000, string $host = '0.0.0.0'): void
    {
        if (!$this->running) {
            throw new \RuntimeException('Driver must be booted before listening');
        }

        $this->startServer($port, $host);
    }

    public function setRouter(Router $router): void
    {
        $this->router = $router;
    }

    abstract protected function startServer(int $port, string $host): void;

    protected function getServerUrl(string $host, int $port): string
    {
        return "{$this->protocol}://{$host}:{$port}";
    }

    protected function logServerStart(string $driverName, string $url): void
    {
        echo "🚀 {$driverName} driver listening on {$url}\n";

        if ($driverName === 'Roadstar') {
            echo "📝 Note: Roadstar uses traditional PHP server. Configure your web server to point to public/index.php\n";
        }
    }

    private function detectProtocol(): void
    {
        // Check if SSL options are provided
        if (isset($this->serverOptions['ssl_cert_file']) && isset($this->serverOptions['ssl_key_file'])) {
            $this->protocol = 'https';
        }
    }

    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    protected function handleFrameworkRequest(Request $request): Response
    {
        if (!$this->router || !$this->dispatcher) {
            return new Response('Router or Dispatcher not initialized', 500);
        }

        $route = $this->router->findRoute(
            $request->getMethod(),
            $request->getPath()
        );

        if (!$route) {
            return new Response('Not Found', 404);
        }

        try {
            // Create middleware pipeline for this request
            $finalHandler = new ControllerRequestHandler($this->container, $this->dispatcher, $route);
            $pipeline = new MiddlewarePipeline($finalHandler);

            // 🆕 Add pre-initialized global middleware (FASTER!)
            $this->pipeGlobalMiddleware($pipeline);

            // Execute the pipeline
            return $pipeline->handle($request);
        } catch (\Throwable $e) {
            if ($this->environment !== 'production') {
                // Log error details in development
                error_log("Middleware pipeline error: " . $e->getMessage());
            }
            return new Response('Server Error: ' . ($this->environment !== 'production' ? $e->getMessage() : 'Internal error'), 500);
        }
    }

    /**
     * 🆕 Pre-initialize global middleware instances during boot
     * This avoids config lookups and DI container calls on every request
     */
    private function initializeGlobalMiddleware(): void
    {
        $globalMiddleware = Config::get('middleware.global', []);
        $this->globalMiddlewareInstances = [];

        foreach ($globalMiddleware as $middlewareClass) {
            if (class_exists($middlewareClass)) {
                try {
                    // Resolve middleware through container (supports dependencies)
                    $this->globalMiddlewareInstances[] = $this->container->get($middlewareClass);
                } catch (\Throwable $e) {
                    // Log error but continue - don't crash the whole app
                    if ($this->environment !== 'production') {
                        error_log("Failed to initialize middleware {$middlewareClass}: " . $e->getMessage());
                    }
                }
            } elseif ($this->environment !== 'production') {
                error_log("Middleware class not found: {$middlewareClass}");
            }
        }
    }

    /**
     * 🆕 Use pre-initialized middleware instances instead of creating new ones
     */
    protected function pipeGlobalMiddleware(MiddlewarePipeline $pipeline): void
    {
        if ($this->globalMiddlewareInstances === null) {
            $this->initializeGlobalMiddleware();
        }

        foreach ($this->globalMiddlewareInstances as $middleware) {
            $pipeline->pipe($middleware);
        }
    }

    /**
     * Get the initialized global middleware instances (for testing)
     */
    public function getGlobalMiddlewareInstances(): array
    {
        if ($this->globalMiddlewareInstances === null) {
            $this->initializeGlobalMiddleware();
        }
        return $this->globalMiddlewareInstances;
    }
}
