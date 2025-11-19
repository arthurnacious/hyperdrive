<?php

declare(strict_types=1);

namespace Hyperdrive\Routing;

use Hyperdrive\Attributes\Http\Route as RouteAttribute;
use Hyperdrive\Attributes\Http\Verbs\Delete;
use Hyperdrive\Attributes\Http\Verbs\Get;
use Hyperdrive\Attributes\Http\Verbs\Patch;
use Hyperdrive\Attributes\Http\Verbs\Post;
use Hyperdrive\Attributes\Http\Verbs\Put;

class Router
{
    /** @var RouteDefinition[] */
    private array $routes = [];

    public function registerController(string $controllerClass): void
    {

        $reflection = new \ReflectionClass($controllerClass);

        // Get class-level Route attribute for prefix
        $classRoute = $this->getClassRoute($reflection);
        $prefix = $classRoute?->prefix ?? '';

        foreach ($reflection->getMethods() as $method) {
            if ($method->isPublic() && !$method->isConstructor()) {
                $this->registerRoute($controllerClass, $method, $prefix);
            }
        }
    }

    public function findRoute(string $method, string $path): ?RouteDefinition
    {
        foreach ($this->routes as $route) {
            if ($route->matches($method, $path)) {
                return $route;
            }
        }

        return null;
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
            // If no path is specified, use empty string (which becomes root when combined with prefix)
            $path = $path ?: '';
            $fullPath = $this->buildPath($prefix, $path);
            $this->routes[] = new RouteDefinition($httpMethod, $fullPath, $controllerClass, $method->getName());
        }
    }

    private function buildPath(string $prefix, string $path): string
    {
        return \Hyperdrive\Support\PathBuilder::build($prefix, $path);
    }

    /**
     * @return RouteDefinition[]
     */
    public function getRegisteredRoutes(): array
    {
        return $this->routes;
    }
}
