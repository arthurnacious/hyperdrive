<?php

declare(strict_types=1);

namespace Hyperdrive\Drivers;

use Hyperdrive\Container\Container;
use Hyperdrive\Http\ControllerDispatcher;
use Hyperdrive\Http\JsonResponse;
use Hyperdrive\Http\Request;
use Hyperdrive\Http\Response;
use Hyperdrive\Routing\Router;

abstract class AbstractServerDriver extends AbstractDriver
{
    protected string $protocol = 'http';
    protected array $serverOptions = [];
    protected ?Router $router = null;
    protected ?Container $container = null;
    protected ?ControllerDispatcher $dispatcher = null;

    public function boot(): void
    {
        $this->running = true;

        // Only initialize router if not already set
        if (!$this->router) {
            $this->router = new Router();
        }

        // Initialize container and dispatcher
        if (!$this->container) {
            $this->container = new Container();
        }

        $this->dispatcher = new ControllerDispatcher($this->container);
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
            $result = $this->dispatcher->dispatch($route, $request);
            return $this->convertToResponse($result);
        } catch (\Throwable $e) {
            return new Response('Server Error: ' . $e->getMessage(), 500);
        }
    }

    private function convertToResponse(mixed $result): Response
    {
        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result) || is_object($result)) {
            return new JsonResponse((array) $result);
        }

        return new Response((string) $result);
    }
}
