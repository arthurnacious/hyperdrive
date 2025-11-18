<?php

declare(strict_types=1);

namespace Hyperdrive\Application;

class ModuleRegistry
{
    private array $modules = [];

    public function register(string $moduleClass): void
    {
        if ($this->has($moduleClass)) {
            return;
        }

        $metadata = $this->resolveModuleMetadata($moduleClass);
        $this->modules[$moduleClass] = $metadata;

        // Register all imported modules recursively
        foreach ($metadata['imports'] as $importedModule) {
            $this->register($importedModule);
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
}
