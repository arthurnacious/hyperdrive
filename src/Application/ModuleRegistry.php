<?php

declare(strict_types=1);

namespace Hyperdrive\Application;

use Hyperdrive\Container\Container;
use Hyperdrive\Exceptions\ModuleBoundaryException;
use Hyperdrive\Routing\Router;
use Hyperdrive\Support\PathBuilder;
use Hyperdrive\WebSocket\WebSocketRegistry;

class ModuleRegistry
{
    private array $modules = [];
    private ?Container $container = null;
    private ?Router $router = null;
    private ?WebSocketRegistry $webSocketRegistry = null;

    public function setContainer(Container $container): void
    {
        $this->container = $container;
    }

    public function setRouter(Router $router): void
    {
        $this->router = $router;
    }

    public function setWebSocketRegistry(WebSocketRegistry $webSocketRegistry): void
    {
        $this->webSocketRegistry = $webSocketRegistry;
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

        if ($this->webSocketRegistry) {
            $this->registerModuleGateways($moduleClass, $fullPrefix);
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

    public function getRegisteredModules(): array
    {
        return array_keys($this->modules);
    }

    /**
     * Boot-time check that every registered controller/injectable's
     * constructor dependencies are actually reachable across module
     * boundaries: a dependency owned by another module must be listed in
     * that module's `exports`, and the consuming module must import the
     * owning module (directly or transitively).
     *
     * This does not change how dependencies are actually resolved (the
     * container remains a single, flat, shared instance) - it only catches,
     * at boot time, places where the module graph's declared boundaries
     * don't match what the code actually depends on.
     *
     * @throws ModuleBoundaryException
     */
    public function validateModuleBoundaries(): void
    {
        $ownership = $this->buildInjectableOwnership();

        foreach ($this->modules as $moduleClass => $metadata) {
            $consumers = array_merge(
                $metadata['controllers'],
                $this->concreteInjectableClasses($metadata['injectables']),
            );

            foreach ($consumers as $consumingClass) {
                if (!class_exists($consumingClass)) {
                    continue;
                }

                foreach ($this->constructorDependencies($consumingClass) as $dependencyClass) {
                    $this->assertDependencyIsReachable($moduleClass, $consumingClass, $dependencyClass, $ownership);
                }
            }
        }
    }

    /**
     * @return array<class-string, class-string> dependency class/interface => owning module class
     */
    private function buildInjectableOwnership(): array
    {
        $ownership = [];

        foreach ($this->modules as $moduleClass => $metadata) {
            foreach ($metadata['injectables'] as $key => $value) {
                if (is_string($key) && interface_exists($key)) {
                    $ownership[$key] = $moduleClass;
                }

                if (class_exists($value)) {
                    $ownership[$value] = $moduleClass;
                }
            }
        }

        return $ownership;
    }

    /**
     * @return class-string[]
     */
    private function concreteInjectableClasses(array $injectables): array
    {
        $classes = array_values(array_filter(array_values($injectables), 'class_exists'));

        return $classes;
    }

    /**
     * @return class-string[]
     */
    private function constructorDependencies(string $class): array
    {
        $reflection = new \ReflectionClass($class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return [];
        }

        $dependencies = [];

        foreach ($constructor->getParameters() as $parameter) {
            $type = $parameter->getType();

            if ($type instanceof \ReflectionNamedType && !$type->isBuiltin()) {
                $dependencies[] = $type->getName();
            }
        }

        return $dependencies;
    }

    /**
     * @param array<class-string, class-string> $ownership
     */
    private function assertDependencyIsReachable(
        string $consumingModule,
        string $consumingClass,
        string $dependencyClass,
        array $ownership
    ): void {
        $owningModule = $ownership[$dependencyClass] ?? null;

        // Nobody declared this as a module injectable (a framework class
        // like Request/Container, or a plain vendor class) - module
        // boundaries don't apply to it.
        if ($owningModule === null || $owningModule === $consumingModule) {
            return;
        }

        if (!in_array($dependencyClass, $this->getExports($owningModule), true)) {
            throw ModuleBoundaryException::notExported($consumingClass, $consumingModule, $dependencyClass, $owningModule);
        }

        if (!in_array($owningModule, $this->transitiveImports($consumingModule), true)) {
            throw ModuleBoundaryException::notImported($consumingClass, $consumingModule, $dependencyClass, $owningModule);
        }
    }

    /**
     * @param array<class-string, true> $seen
     * @return class-string[]
     */
    private function transitiveImports(string $moduleClass, array $seen = []): array
    {
        if (isset($seen[$moduleClass])) {
            return [];
        }

        $seen[$moduleClass] = true;

        $imports = $this->getImports($moduleClass);
        $all = $imports;

        foreach ($imports as $importedModule) {
            $all = array_merge($all, $this->transitiveImports($importedModule, $seen));
        }

        return array_values(array_unique($all));
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
            if (class_exists($controllerClass)) {
                $this->router->registerController($controllerClass, $prefix);
            }
        }
    }

    private function registerModuleGateways(string $moduleClass, string $prefix): void
    {
        foreach ($this->getGateways($moduleClass) as $gatewayClass) {
            if (class_exists($gatewayClass)) {
                $this->webSocketRegistry->registerGateway($gatewayClass, $prefix);
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
