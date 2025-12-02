<?php

declare(strict_types=1);

namespace Hyperdrive\Routing;

use Hyperdrive\Attributes\Http\Route as RouteAttribute;
use Hyperdrive\Attributes\Http\Verbs\Delete;
use Hyperdrive\Attributes\Http\Verbs\Get;
use Hyperdrive\Attributes\Http\Verbs\Patch;
use Hyperdrive\Attributes\Http\Verbs\Post;
use Hyperdrive\Attributes\Http\Verbs\Put;
use Hyperdrive\Attributes\Middleware as MiddlewareAttribute;
use Hyperdrive\Support\PathBuilder;

class Router
{
    /** @var RouteDefinition[] */
    private array $routes = [];

    /** @var array<string, RouteDefinition> */
    private array $routeMap = []; // Fast lookup: method+path → RouteDefinition

    /** @var bool */
    private bool $mapBuilt = false; // Track if map is current

    public function registerController(string $controllerClass, string $prefix = ''): void
    {
        $reflection = new \ReflectionClass($controllerClass);

        // Get class-level Route attribute for TEST COMPATIBILITY
        $classRoute = $this->getClassRoute($reflection);
        $controllerPrefix = $classRoute?->prefix ?? '';

        // Combine module prefix + controller prefix
        $fullPrefix = PathBuilder::build($prefix, $controllerPrefix);

        foreach ($reflection->getMethods() as $method) {
            if ($method->isPublic() && !$method->isConstructor()) {
                $this->registerRoute($controllerClass, $method, $fullPrefix);
            }
        }

        // Invalidate route map since we added new routes
        $this->mapBuilt = false;
    }

    /**
     * Build fast lookup map from registered routes
     */
    public function buildRouteMap(): void
    {
        $this->routeMap = [];

        foreach ($this->routes as $route) {
            // Only add static routes to the fast map
            if (!$this->routeHasParameters($route->getPath())) {
                $key = $this->generateRouteKey($route->getMethod(), $route->getPath());
                $this->routeMap[$key] = $route;
            }
        }

        $this->mapBuilt = true;
    }

    /**
     * Generate consistent cache key for route lookup
     */
    private function generateRouteKey(string $method, string $path): string
    {
        return strtoupper($method) . ':' . $path;
    }

    /**
     * Check if route path contains parameters (like {id})
     */
    private function routeHasParameters(string $path): bool
    {
        return str_contains($path, '{') && str_contains($path, '}');
    }

    /**
     * Fallback to pattern matching for parameterized routes
     */
    private function findRouteByPattern(string $method, string $path): ?RouteDefinition
    {
        foreach ($this->routes as $route) {
            if ($route->matches($method, $path)) {
                return $route;
            }
        }

        return null;
    }

    public function findRoute(string $method, string $path): ?RouteDefinition
    {
        // Use fast map lookup first
        if (!$this->mapBuilt) {
            $this->buildRouteMap();
        }

        $key = $this->generateRouteKey($method, $path);

        // Fast O(1) lookup
        if (isset($this->routeMap[$key])) {
            return $this->routeMap[$key];
        }

        // Fallback to pattern matching for parameterized routes
        return $this->findRouteByPattern($method, $path);
    }

    private function getClassRoute(\ReflectionClass $reflection): ?RouteAttribute
    {
        $attributes = $reflection->getAttributes(RouteAttribute::class);
        if (empty($attributes)) {
            return null;
        }
        return $attributes[0]->newInstance();
    }

    private function registerRoute(string $controllerClass, \ReflectionMethod $method, string $prefix): void
    {
        $httpMethod = null;
        $path = '';

        // Check for HTTP method attributes
        foreach ($method->getAttributes() as $attribute) {
            $instance = $attribute->newInstance();

            switch (get_class($instance)) {
                case Get::class:
                    $httpMethod = 'GET';
                    $path = $instance->path;
                    break;
                case Post::class:
                    $httpMethod = 'POST';
                    $path = $instance->path;
                    break;
                case Put::class:
                    $httpMethod = 'PUT';
                    $path = $instance->path;
                    break;
                case Delete::class:
                    $httpMethod = 'DELETE';
                    $path = $instance->path;
                    break;
                case Patch::class:
                    $httpMethod = 'PATCH';
                    $path = $instance->path;
                    break;
            }
        }

        if ($httpMethod !== null) {
            $fullPath = PathBuilder::build($prefix, $path ?: '');

            // Collect middleware for this route
            $routeMiddlewares = $this->collectRouteMiddlewares($controllerClass, $method);

            $this->routes[] = new RouteDefinition(
                $httpMethod,
                $fullPath,
                $controllerClass,
                $method->getName(),
                $routeMiddlewares
            );

            // Invalidate map since we added a route
            $this->mapBuilt = false;
        }
    }

    /**
     * Collect middleware from controller class and method
     * Method-specific middleware overrides/extends controller middleware
     * 
     * @param string $controllerClass
     * @param \ReflectionMethod $method
     * @return class-string<\Hyperdrive\Http\Middleware\MiddlewareInterface>[]
     */

    private function collectRouteMiddlewares(string $controllerClass, \ReflectionMethod $method): array
    {
        $middlewares = [];

        // 1. Get controller-level middlewares
        $reflectionClass = new \ReflectionClass($controllerClass);
        $controllerMiddlewareAttrs = $reflectionClass->getAttributes(MiddlewareAttribute::class);

        foreach ($controllerMiddlewareAttrs as $attr) {
            $middlewareAttr = $attr->newInstance();
            $middlewares = array_merge($middlewares, $middlewareAttr->middlewares);
        }

        // 2. Get method-level middlewares
        $methodMiddlewareAttrs = $method->getAttributes(MiddlewareAttribute::class);

        foreach ($methodMiddlewareAttrs as $attr) {
            $middlewareAttr = $attr->newInstance();
            $middlewares = array_merge($middlewares, $middlewareAttr->middlewares);
        }

        // Remove duplicates while preserving order
        $uniqueMiddlewares = [];
        foreach ($middlewares as $mwClass) {
            if (!in_array($mwClass, $uniqueMiddlewares, true)) {
                $uniqueMiddlewares[] = $mwClass;
            }
        }

        return $uniqueMiddlewares;
    }



    /**
     * @return RouteDefinition[]
     */
    public function getRegisteredRoutes(): array
    {
        return $this->routes;
    }

    /**
     * Get route map stats for debugging
     */
    public function getRouteMapStats(): array
    {
        return [
            'total_routes' => count($this->routes),
            'static_routes' => count($this->routeMap),
            'parameterized_routes' => count($this->routes) - count($this->routeMap),
            'map_built' => $this->mapBuilt
        ];
    }

    /**
     * Debug method to see middleware for routes
     */
    public function getRoutesWithMiddleware(): array
    {
        $result = [];
        foreach ($this->routes as $route) {
            $result[] = [
                'method' => $route->getMethod(),
                'path' => $route->getPath(),
                'controller' => $route->getControllerClass() . '::' . $route->getMethodName(),
                'middleware' => $route->getMiddleware(),
            ];
        }
        return $result;
    }
}
