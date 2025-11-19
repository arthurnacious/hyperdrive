<?php

declare(strict_types=1);

namespace Hyperdrive\Application;

use Hyperdrive\Container\Container;

class ModuleRegistry
{
    private array $modules = [];
    private ?Container $container = null;

    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    public function register(string $moduleClass): void
    {
        if ($this->has($moduleClass)) {
            return;
        }

        $metadata = $this->resolveModuleMetadata($moduleClass);
        $this->modules[$moduleClass] = $metadata;

        // Register all imported modules recursively FIRST
        foreach ($metadata['imports'] as $importedModule) {
            $this->register($importedModule);
        }

        // THEN register bindings and injectables with the container
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

    private function resolveModuleMetadata(string $moduleClass): array
    {
        $reflection = new \ReflectionClass($moduleClass);
        $attributes = $reflection->getAttributes(Module::class);

        if (empty($attributes)) {
            return ['imports' => [], 'controllers' => [], 'injectables' => [], 'exports' => []];
        }

        $moduleAttribute = $attributes[0]->newInstance();

        return [
            'imports' => $moduleAttribute->imports,
            'controllers' => $moduleAttribute->controllers,
            'injectables' => $moduleAttribute->injectables,
            'exports' => $moduleAttribute->exports,
        ];
    }

    private function registerModuleBindings(array $metadata): void
    {
        // First, register all interface bindings
        foreach ($metadata['injectables'] as $key => $value) {
            if (is_string($key)) {
                // This is an interface => implementation binding
                $this->container->bind($key, $value);
            }
        }

        // Then, register concrete classes (which may depend on the interface bindings)
        foreach ($metadata['injectables'] as $key => $value) {
            if (!is_string($key)) {
                // This is a concrete class registration
                $this->container->get($value); // This will auto-register it
            }
        }
    }
}
