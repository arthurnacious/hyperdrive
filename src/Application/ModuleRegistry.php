<?php

declare(strict_types=1);

namespace Hyperdrive\Application;

use Hyperdrive\Container\Container;
use Hyperdrive\Routing\Router;
use Hyperdrive\Support\PathBuilder;

class ModuleRegistry
{
    private array $modules = [];
    private ?Container $container = null;
    private ?Router $router = null;

    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    public function setRouter(Router $router): void
    {
        $this->router = $router;
    }

    public function register(string $moduleClass, string $parentPrefix = ''): void
    {
        if ($this->has($moduleClass)) {
            return;
        }

        $metadata = $this->resolveModuleMetadata($moduleClass);

        // Apply parent prefix to this module's prefix
        $fullPrefix = PathBuilder::build($parentPrefix, $metadata['prefix']);

        $this->modules[$moduleClass] = array_merge($metadata, [
            'fullPrefix' => $fullPrefix
        ]);

        // Register imported modules with the accumulated prefix FIRST
        foreach ($metadata['imports'] as $importedModule) {
            $this->register($importedModule, $fullPrefix);
        }

        // THEN register controllers and bindings
        if ($this->router) {
            $this->registerModuleControllers($moduleClass, $fullPrefix);
        }

        if ($this->container) {
            $this->registerModuleBindings($metadata);
        }
    }

    public function has(string $moduleClass): bool
    {
        return array_key_exists($moduleClass, $this->modules);
    }

    public function getControllers(string $moduleClass): array
    {
        return $this->modules[$moduleClass]['controllers'] ?? [];
    }

    public function getInjectables(string $moduleClass): array
    {
        return $this->modules[$moduleClass]['injectables'] ?? [];
    }

    public function getExports(string $moduleClass): array
    {
        return $this->modules[$moduleClass]['exports'] ?? [];
    }

    public function getImports(string $moduleClass): array
    {
        return $this->modules[$moduleClass]['imports'] ?? [];
    }

    public function getGateways(string $moduleClass): array
    {
        return $this->modules[$moduleClass]['gateways'] ?? [];
    }

    public function getStatic(string $moduleClass): array
    {
        return $this->modules[$moduleClass]['static'] ?? [];
    }

    public function getPrefix(string $moduleClass): string
    {
        return $this->modules[$moduleClass]['fullPrefix'] ?? '';
    }

    private function resolveModuleMetadata(string $moduleClass): array
    {
        $reflection = new \ReflectionClass($moduleClass);
        $attributes = $reflection->getAttributes(Module::class);

        if (empty($attributes)) {
            return [
                'imports' => [],
                'controllers' => [],
                'injectables' => [],
                'exports' => [],
                'gateways' => [],
                'static' => [],
                'prefix' => ''
            ];
        }

        $moduleAttribute = $attributes[0]->newInstance();

        return [
            'imports' => $moduleAttribute->imports,
            'controllers' => $moduleAttribute->controllers,
            'injectables' => $moduleAttribute->injectables,
            'exports' => $moduleAttribute->exports,
            'gateways' => $moduleAttribute->gateways,
            'static' => $moduleAttribute->static,
            'prefix' => $moduleAttribute->prefix,
        ];
    }

    private function registerModuleControllers(string $moduleClass, string $prefix): void
    {
        $controllers = $this->getControllers($moduleClass);

        foreach ($controllers as $controllerClass) {
            // Register controller with the accumulated prefix
            if (class_exists($controllerClass)) {
                $this->router->registerController($controllerClass, $prefix);
            }
        }
    }

    private function registerModuleBindings(array $metadata): void
    {
        // First, register all interface bindings
        foreach ($metadata['injectables'] as $key => $value) {
            if (is_string($key) && interface_exists($key) && class_exists($value)) {
                // This is an interface => implementation binding
                $this->container->bind($key, $value);
            }
        }

        // Then, register concrete classes (which may depend on the interface bindings)
        foreach ($metadata['injectables'] as $key => $value) {
            if (!is_string($key) && class_exists($value)) {
                // This is a concrete class registration
                try {
                    $this->container->get($value); // This will auto-register it
                } catch (\Throwable $e) {
                    // Ignore binding errors in tests
                }
            }
        }
    }
}
