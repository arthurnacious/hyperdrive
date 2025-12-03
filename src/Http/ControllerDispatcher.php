<?php

declare(strict_types=1);

namespace Hyperdrive\Http;

use Hyperdrive\Container\Container;
use Hyperdrive\Http\Dto\Validation\ValidationException;
use Hyperdrive\Routing\OptionsRoute;
use Hyperdrive\Routing\RouteDefinition;

class ControllerDispatcher
{
    private array $controllerPool = [];
    private array $parameterCache = []; // Cache reflection results

    public function __construct(
        private Container $container
    ) {}

    public function dispatch(RouteDefinition $route, Request $request): mixed
    {

        if ($route instanceof OptionsRoute) {
            // Return proper OPTIONS response
            return new Response('', 204, [
                'Allow' => implode(', ', $route->getAllowedMethods())
            ]);
        }

        $controllerClass = $route->getControllerClass();

        // Reuse controller instance (stateless = safe to reuse)
        if (!isset($this->controllerPool[$controllerClass])) {
            $this->controllerPool[$controllerClass] = $this->container->get($controllerClass);
        }

        $controller = $this->controllerPool[$controllerClass];
        $parameters = $this->resolveMethodParameters($controller, $route->getMethodName(), $route, $request);

        return $controller->{$route->getMethodName()}(...$parameters);
    }

    private function resolveMethodParameters(
        object $controller,
        string $methodName,
        RouteDefinition $route,
        Request $request
    ): array {
        $cacheKey = get_class($controller) . '::' . $methodName;

        // Use cached reflection data (avoid reflection on every request)
        if (!isset($this->parameterCache[$cacheKey])) {
            $reflection = new \ReflectionMethod($controller, $methodName);
            $this->parameterCache[$cacheKey] = $reflection->getParameters();
        }

        $parameters = [];
        foreach ($this->parameterCache[$cacheKey] as $parameter) {
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

        // 3. DTO objects (from request body) - WITH VALIDATION EXCEPTION HANDLING
        if (is_subclass_of($typeName, Dto::class)) {
            try {
                return new $typeName($request->getBody());
            } catch (ValidationException $e) {
                //Re-throw ValidationException so it becomes a 422 response
                throw $e;
            } catch (\Throwable $e) {
                // Other DTO errors become 500
                throw new \RuntimeException("DTO creation failed: " . $e->getMessage(), 0, $e);
            }
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

    /**
     * Get cache stats for debugging
     */
    public function getCacheStats(): array
    {
        return [
            'controllers_cached' => count($this->controllerPool),
            'methods_cached' => count($this->parameterCache)
        ];
    }
}
