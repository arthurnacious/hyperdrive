<?php
// src/Http/Middleware/ControllerRequestHandler.php

declare(strict_types=1);

namespace Hyperdrive\Http\Middleware;

use Hyperdrive\Container\Container;
use Hyperdrive\Http\ControllerDispatcher;
use Hyperdrive\Http\Request;
use Hyperdrive\Http\Response;
use Hyperdrive\Routing\RouteDefinition;

class ControllerRequestHandler implements RequestHandlerInterface
{
    public function __construct(
        private Container $container,
        private ControllerDispatcher $dispatcher,
        private RouteDefinition $route
    ) {}

    public function handle(Request $request): Response
    {
        // Create a final handler that calls dispatch()
        $dispatchHandler = new class($this) implements RequestHandlerInterface {
            public function __construct(
                private ControllerRequestHandler $handler
            ) {}

            public function handle(Request $request): Response
            {
                return $this->handler->dispatch($request);
            }
        };

        // Create pipeline with route-specific middlewares
        $pipeline = new MiddlewarePipeline($dispatchHandler);

        // Add route-specific middlewares to pipeline
        $this->addRouteMiddlewares($pipeline);

        // Execute the pipeline
        return $pipeline->handle($request);
    }

    /**
     * Actual controller dispatch (called by pipeline)
     */
    public function dispatch(Request $request): Response
    {
        $result = $this->dispatcher->dispatch($this->route, $request);

        // Convert controller return value to response
        if ($result instanceof Response) {
            return $result;
        }

        if (is_array($result) || is_object($result)) {
            return new \Hyperdrive\Http\JsonResponse((array) $result);
        }

        return new Response((string) $result);
    }

    /**
     * Add route-specific middlewares to pipeline
     */
    private function addRouteMiddlewares(MiddlewarePipeline $pipeline): void
    {
        foreach ($this->route->getMiddlewares() as $middlewareClass) {
            if (class_exists($middlewareClass)) {
                try {
                    $middleware = $this->container->get($middlewareClass);
                    if ($middleware instanceof MiddlewareInterface) {
                        $pipeline->pipe($middleware);
                    }
                } catch (\Throwable $e) {
                    // Log error but continue
                    error_log("Failed to initialize route middleware {$middlewareClass}: " . $e->getMessage());
                }
            }
        }
    }
}
