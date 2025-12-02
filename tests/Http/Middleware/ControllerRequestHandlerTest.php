<?php

declare(strict_types=1);

namespace Hyperdrive\Http\Middleware;

use Hyperdrive\Container\Container;
use Hyperdrive\Http\ControllerDispatcher;
use Hyperdrive\Http\Request;
use Hyperdrive\Http\Response;
use Hyperdrive\Routing\RouteDefinition;

class ControllerRequestHandler
{
    public function __construct(
        private Container $container,
        private ControllerDispatcher $dispatcher,
        private RouteDefinition $route
    ) {}

    /**
     * Handle the request with route-specific middlewares
     */
    public function handle(Request $request): Response
    {
        // Create a final handler that just dispatches to the controller
        $finalHandler = new class($this->dispatcher, $this->route) implements RequestHandlerInterface {
            public function __construct(
                private ControllerDispatcher $dispatcher,
                private RouteDefinition $route
            ) {}

            public function handle(Request $request): Response
            {
                return $this->dispatcher->dispatch($this->route, $request);
            }
        };

        // Create pipeline with route-specific middlewares
        $pipeline = new MiddlewarePipeline($finalHandler);

        // Add route-specific middlewares to pipeline
        $this->addRouteMiddlewares($pipeline);

        // Execute the pipeline
        return $pipeline->handle($request);
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
