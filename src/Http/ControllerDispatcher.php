<?php

declare(strict_types=1);

namespace Hyperdrive\Http;

use Hyperdrive\Container\Container;
use Hyperdrive\Routing\RouteDefinition;

class ControllerDispatcher
{
    public function __construct(
        private Container $container
    ) {}

    public function dispatch(RouteDefinition $route, Request $request): mixed
    {
        $controllerClass = $route->getControllerClass();
        $methodName = $route->getMethodName();

        // Get controller instance from DI container
        $controller = $this->container->get($controllerClass);

        // Resolve method parameters
        $parameters = $this->resolveMethodParameters($controller, $methodName, $route, $request);

        // Call the controller method
        return $controller->$methodName(...$parameters);
    }

    private function resolveMethodParameters(
        object $controller,
        string $methodName,
        RouteDefinition $route,
        Request $request
    ): array {
        $reflection = new \ReflectionMethod($controller, $methodName);
        $parameters = [];

        foreach ($reflection->getParameters() as $parameter) {
            $parameters[] = $this->resolveParameter($parameter, $route, $request);
        }

        return $parameters;
    }

    private function resolveParameter(
        \ReflectionParameter $parameter,
        RouteDefinition $route,
        Request $request
    ): mixed {
        $type = $parameter->getType();

        // Handle parameters without type hints
        if ($type === null) {
            throw new \InvalidArgumentException(
                "Cannot resolve parameter \${$parameter->getName()} without type hint in {$parameter->getDeclaringClass()?->getName()}::{$parameter->getDeclaringFunction()->getName()}"
            );
        }

        // Handle built-in types (int, string, bool, float, etc.)
        if ($type instanceof \ReflectionNamedType && $type->isBuiltin()) {
            // 1. Try route parameters first (/{id} → $id)
            $routeParams = $route->extractParameters($request->getPath());

            // Debug
            echo "DEBUG: Route path: '{$route->getPath()}', Request path: '{$request->getPath()}'\n";
            echo "DEBUG: Route params: " . json_encode($routeParams) . "\n";
            echo "DEBUG: Looking for parameter: '{$parameter->getName()}'\n";

            if (isset($routeParams[$parameter->getName()])) {
                return $this->convertRouteParameter($routeParams[$parameter->getName()], $type->getName());
            }

            // 2. Try request attributes (from middleware)
            $attributeValue = $request->getAttribute($parameter->getName());
            if ($attributeValue !== null) {
                return $this->convertRouteParameter($attributeValue, $type->getName());
            }

            throw new \InvalidArgumentException(
                "Cannot resolve primitive parameter \${$parameter->getName()} of type {$type->getName()}. " .
                    "It must come from route parameters or request attributes."
            );
        }

        if (!$type instanceof \ReflectionNamedType) {
            throw new \InvalidArgumentException(
                "Cannot resolve parameter \${$parameter->getName()} without type hint"
            );
        }

        $typeName = $type->getName();

        // 1. Route parameters first (/{id} → $id)
        $routeParams = $route->extractParameters($request->getPath());
        if (isset($routeParams[$parameter->getName()])) {
            return $this->convertRouteParameter($routeParams[$parameter->getName()], $typeName);
        }

        // 2. Request object
        if ($typeName === Request::class || is_subclass_of($typeName, Request::class)) {
            return $request;
        }

        // 3. DTO objects (from request body)
        if (is_subclass_of($typeName, Dto::class)) {
            return new $typeName($request->getBody());
        }

        // 4. DI container resolution
        return $this->container->get($typeName);
    }

    private function convertRouteParameter(string $value, string $type): mixed
    {
        return match ($type) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            default => $value
        };
    }
}
