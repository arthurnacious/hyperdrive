<?php
// src/Http/Middleware/ControllerRequestHandler.php

declare(strict_types=1);

namespace Hyperdrive\Http\Middleware;

use Hyperdrive\Container\Container;
use Hyperdrive\Http\ControllerDispatcher;
use Hyperdrive\Http\Request;
use Hyperdrive\Http\Response;
use Hyperdrive\Routing\RouteDefinition;
use Hyperdrive\View\ViewEngineInterface;

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

        // #[Render] routes hand their data to the bound view engine
        if ($this->route->getView() !== null && ($result === null || is_array($result) || is_object($result))) {
            $data = is_object($result) ? get_object_vars($result) : ($result ?? []);
            return $this->render($this->route->getView(), $data);
        }

        if (is_array($result) || is_object($result)) {
            return new \Hyperdrive\Http\JsonResponse((array) $result);
        }

        return new Response((string) $result);
    }

    private function render(string $view, array $data): Response
    {
        if (!$this->container->has(ViewEngineInterface::class)) {
            throw new \RuntimeException(
                "{$this->route->getControllerClass()}::{$this->route->getMethodName()} uses #[Render('{$view}')] "
                . "but no ViewEngineInterface is bound - bind one in a module's injectables"
            );
        }

        $html = $this->container->get(ViewEngineInterface::class)->render($view, $data);

        return new Response($html, 200, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    /**
     * Add route-specific middlewares to pipeline.
     *
     * Fails closed: a middleware that is missing, isn't a MiddlewareInterface
     * or can't be constructed throws, rather than letting the route run
     * without it - route middleware is typically what guards the route.
     */
    private function addRouteMiddlewares(MiddlewarePipeline $pipeline): void
    {
        foreach ($this->route->getMiddlewares() as $middlewareClass) {
            if (!class_exists($middlewareClass)) {
                throw new \RuntimeException("Route middleware {$middlewareClass} does not exist");
            }

            try {
                $middleware = $this->container->get($middlewareClass);
            } catch (\Throwable $e) {
                throw new \RuntimeException("Failed to initialize route middleware {$middlewareClass}: " . $e->getMessage(), 0, $e);
            }

            if (!$middleware instanceof MiddlewareInterface) {
                throw new \RuntimeException("Route middleware {$middlewareClass} does not implement " . MiddlewareInterface::class);
            }

            $pipeline->pipe($middleware);
        }
    }
}
