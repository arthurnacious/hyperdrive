<?php

declare(strict_types=1);

namespace Hyperdrive\Routing;

class RouteDefinition
{
    public function __construct(
        private string $method,
        private string $path,
        private string $controllerClass,
        private string $methodName
    ) {}

    public function matches(string $method, string $path): bool
    {
        if ($this->method !== $method) {
            return false;
        }

        $pattern = $this->buildPattern($this->path);
        return preg_match($pattern, $path) === 1;
    }

    public function getMethodName(): string
    {
        return $this->methodName;
    }

    public function getControllerClass(): string
    {
        return $this->controllerClass;
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function extractParameters(string $path): array
    {
        $parameters = [];
        $pattern = $this->buildPattern($this->path);

        if (preg_match($pattern, $path, $matches)) {
            foreach ($matches as $key => $value) {
                if (is_string($key)) {
                    $parameters[$key] = $value;
                }
            }
        }

        return $parameters;
    }

    private function buildPattern(string $routePath): string
    {
        // Handle root path specially
        if ($routePath === '/') {
            return '#^/$#';
        }

        $pattern = preg_replace('/\{([a-zA-Z_][a-zA-Z0-9_]*)\}/', '(?P<$1>[^/]+)', $routePath);
        return '#^' . $pattern . '$#';
    }
}
