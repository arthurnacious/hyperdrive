<?php

declare(strict_types=1);

namespace Hyperdrive\Container;

class Container
{
    private array $instances = [];
    private array $bindings = [];
    private array $resolving = []; // Track currently resolving classes

    public function get(string $id): object
    {
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // Check if we're already resolving this class (circular dependency)
        if (isset($this->resolving[$id])) {
            throw new ContainerException("Circular dependency detected while resolving: {$id}");
        }

        $this->resolving[$id] = true;

        try {
            // Check if there's a binding for this interface/abstract
            if (isset($this->bindings[$id])) {
                return $this->get($this->bindings[$id]);
            }

            return $this->resolve($id);
        } finally {
            // Always remove from resolving stack
            unset($this->resolving[$id]);
        }
    }

    public function has(string $id): bool
    {
        return isset($this->instances[$id]) ||
            isset($this->bindings[$id]) ||
            class_exists($id);
    }

    public function bind(string $abstract, string $concrete): void
    {
        $this->bindings[$abstract] = $concrete;
    }

    public function singleton(string $abstract, ?string $concrete = null): void
    {
        if ($concrete === null) {
            $concrete = $abstract;
        }

        $this->bindings[$abstract] = $concrete;
        // Force instantiation and store as singleton
        $this->instances[$abstract] = $this->get($concrete);
    }

    private function resolve(string $class): object
    {
        try {
            $reflection = new \ReflectionClass($class);
        } catch (\ReflectionException $e) {
            throw new ContainerException("Class {$class} does not exist", 0, $e);
        }

        if (!$reflection->isInstantiable()) {
            throw new ContainerException("Class {$class} is not instantiable");
        }

        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            $instance = new $class();
            $this->instances[$class] = $instance;
            return $instance;
        }

        $parameters = $constructor->getParameters();
        $dependencies = $this->resolveDependencies($parameters);

        $instance = $reflection->newInstanceArgs($dependencies);
        $this->instances[$class] = $instance;

        return $instance;
    }

    private function resolveDependencies(array $parameters): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $type = $parameter->getType();

            if ($type === null || $type->isBuiltin()) {
                throw new ContainerException(
                    "Cannot resolve primitive parameter \${$parameter->getName()} in {$parameter->getDeclaringClass()?->getName()}"
                );
            }

            $dependencies[] = $this->get($type->getName());
        }

        return $dependencies;
    }
}
