<?php

declare(strict_types=1);

namespace Hyperdrive\Application;

use Hyperdrive\Config\Config;
use Hyperdrive\Config\Environment;
use Hyperdrive\Container\Container;
use Hyperdrive\Contracts\DriverInterface;
use Hyperdrive\Drivers\OpenSwooleDriver;
use Hyperdrive\Drivers\RoadstarDriver;
use Hyperdrive\Drivers\SwooleDriver;
use Hyperdrive\Exceptions\DriverNotFoundException;
use Hyperdrive\Routing\Router;

final class Hyperdrive
{
    private DriverInterface $driver;
    private string $environment;
    private Container $container;
    private Router $router;
    private ModuleRegistry $moduleRegistry;

    private function __construct(
        private string $rootModule,
        string $driver,
        string $environment,
        private string $url = 'http://localhost:3000'
    ) {
        $this->environment = $environment;
        $this->container = new Container();
        $this->router = new Router();
        $this->moduleRegistry = new ModuleRegistry();

        // Set up dependencies
        $this->moduleRegistry->setContainer($this->container);
        $this->moduleRegistry->setRouter($this->router);

        $this->driver = $this->resolveDriver($driver);
    }

    public static function create(
        string $rootModule,
        string $driver = 'auto',
        string $environment = 'production',
        string $url = 'http://localhost:3000'
    ): self {
        return new self($rootModule, $driver, $environment, $url);
    }

    public function boot(): void
    {
        // Set the environment
        Environment::setTesting($this->environment === 'testing');

        // Load configuration
        $this->loadConfiguration();

        // Set app URL in config
        Config::set('app.url', $this->url);

        // Initialize the module tree
        $this->initializeModules();

        // Set up the driver with dependencies
        $this->driver->setContainer($this->container);
        $this->driver->setRouter($this->router);

        // Boot the driver
        $this->driver->boot();

        // 🆕 Environment-aware logging
        if ($this->environment !== 'production') {
            $this->logBootInfo();
        }
    }

    // 🆕 Separate method for development logging
    private function logBootInfo(): void
    {
        echo "🚀 Hyperdrive booted successfully!\n";
        echo "   Environment: {$this->environment}\n";
        echo "   URL: {$this->url}\n";
        echo "   Driver: " . get_class($this->driver) . "\n";

        // Display registered routes (development only)
        $this->displayRoutes();
    }


    public function listen(int $port = 3000, string $host = '0.0.0.0'): void
    {
        $this->driver->listen($port, $host);
    }

    public function getDriver(): DriverInterface
    {
        return $this->driver;
    }

    public function getContainer(): Container
    {
        return $this->container;
    }

    public function getRouter(): Router
    {
        return $this->router;
    }

    private function resolveDriver(string $driver): DriverInterface
    {
        if ($driver === 'auto') {
            return $this->autoDetectDriver();
        }

        return match ($driver) {
            'openswoole' => $this->createOpenSwooleDriver(),
            'swoole' => $this->createSwooleDriver(),
            'roadstar' => $this->createRoadstarDriver(),
            default => throw new DriverNotFoundException("Driver {$driver} is not supported")
        };
    }

    private function autoDetectDriver(): DriverInterface
    {
        if (extension_loaded('openswoole')) {
            return $this->createOpenSwooleDriver();
        }

        if (extension_loaded('swoole')) {
            return $this->createSwooleDriver();
        }

        return $this->createRoadstarDriver();
    }

    private function createOpenSwooleDriver(): DriverInterface
    {
        if (!extension_loaded('openswoole')) {
            throw new DriverNotFoundException('OpenSwoole extension is not installed');
        }

        return new OpenSwooleDriver($this->rootModule, $this->environment);
    }

    private function createSwooleDriver(): DriverInterface
    {
        if (!extension_loaded('swoole')) {
            throw new DriverNotFoundException('Swoole extension is not installed');
        }

        return new SwooleDriver($this->rootModule, $this->environment);
    }

    private function createRoadstarDriver(): DriverInterface
    {
        return new RoadstarDriver($this->rootModule, $this->environment);
    }

    private function loadConfiguration(): void
    {
        // Load framework defaults
        $frameworkConfigPath = __DIR__ . '/../../config';
        if (is_dir($frameworkConfigPath)) {
            \Hyperdrive\Config\ConfigLoader::loadFromDirectory($frameworkConfigPath);
        }

        // Load project overrides (from project root)
        $projectConfigPath = getcwd() . '/config';
        if (is_dir($projectConfigPath)) {
            \Hyperdrive\Config\ConfigLoader::loadFromDirectory($projectConfigPath);
        }

        // Set environment-specific config
        $envConfigPath = getcwd() . '/config/' . $this->environment;
        if (is_dir($envConfigPath)) {
            \Hyperdrive\Config\ConfigLoader::loadFromDirectory($envConfigPath);
        }
    }

    private function initializeModules(): void
    {
        // Register the root module and all its imports recursively
        $this->moduleRegistry->register($this->rootModule);

        echo "📦 Modules initialized:\n";
        foreach ($this->moduleRegistry->getRegisteredModules() as $moduleClass) {
            $controllers = $this->moduleRegistry->getControllers($moduleClass);
            $prefix = $this->moduleRegistry->getPrefix($moduleClass);
            echo "   - {$moduleClass} (prefix: '{$prefix}', controllers: " . count($controllers) . ")\n";
        }
    }

    private function displayRoutes(): void
    {
        $routes = $this->router->getRegisteredRoutes();
        if (empty($routes)) {
            echo "   No routes registered\n";
            return;
        }

        echo "   Routes:\n";
        foreach ($routes as $route) {
            echo "     {$route->getMethod()} {$route->getPath()} → {$route->getControllerClass()}::{$route->getMethodName()}\n";
        }
    }
}
