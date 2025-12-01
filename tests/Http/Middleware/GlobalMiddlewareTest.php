<?php
// tests/Http/Middleware/GlobalMiddlewareTest.php

declare(strict_types=1);

namespace Hyperdrive\Tests\Http\Middleware;

use Hyperdrive\Config\Config;
use Hyperdrive\Container\Container;
use Hyperdrive\Http\Middleware\ControllerRequestHandler;
use Hyperdrive\Http\Middleware\MiddlewareInterface;
use Hyperdrive\Http\Middleware\MiddlewarePipeline;
use Hyperdrive\Http\Middleware\RequestHandlerInterface;
use Hyperdrive\Http\Request;
use Hyperdrive\Http\Response;
use Hyperdrive\Routing\RouteDefinition;
use PHPUnit\Framework\TestCase;

class TestGlobalMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, RequestHandlerInterface $handler): Response
    {
        $request = $request->withAttribute('global_middleware_applied', true);
        $response = $handler->handle($request);

        // Add a header to show middleware was processed
        $headers = $response->getHeaders();
        $headers['X-Global-Middleware'] = 'processed';

        return new Response(
            $response->getContent(),
            $response->getStatusCode(),
            $headers
        );
    }
}

class TestControllerHandler implements RequestHandlerInterface
{
    public function handle(Request $request): Response
    {
        $middlewareApplied = $request->getAttribute('global_middleware_applied', false);

        return new Response(
            $middlewareApplied ? 'Middleware applied' : 'No middleware',
            200,
            ['Content-Type' => 'text/plain']
        );
    }
}

class GlobalMiddlewareTest extends TestCase
{
    protected function setUp(): void
    {
        Config::clear();
    }

    public function test_it_loads_global_middleware_from_config(): void
    {
        // Set up global middleware config
        Config::set('middleware.global', [
            TestGlobalMiddleware::class
        ]);

        $this->assertEquals([
            TestGlobalMiddleware::class
        ], Config::get('middleware.global'));
    }

    public function test_global_middleware_can_be_empty(): void
    {
        Config::set('middleware.global', []);

        $this->assertEquals([], Config::get('middleware.global'));
    }

    public function test_global_middleware_executes_in_pipeline(): void
    {
        // Create a pipeline with global middleware
        $finalHandler = new TestControllerHandler();
        $pipeline = new MiddlewarePipeline($finalHandler);

        // Add global middleware to pipeline
        $middleware = new TestGlobalMiddleware();
        $pipeline->pipe($middleware);

        // Create a request
        $request = new Request();

        // Execute the pipeline
        $response = $pipeline->handle($request);

        // Assert middleware was applied
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('Middleware applied', $response->getContent());
        $this->assertEquals('processed', $response->getHeaders()['X-Global-Middleware'] ?? null);
    }

    public function test_multiple_global_middleware_execute_in_order(): void
    {
        // Create middleware that add to a stack
        $executionOrder = [];

        $middleware1 = new class($executionOrder) implements MiddlewareInterface {
            private array $order;
            public function __construct(array &$order)
            {
                $this->order = &$order;
            }
            public function handle(Request $request, RequestHandlerInterface $handler): Response
            {
                $this->order[] = 'middleware1';
                $request = $request->withAttribute('mw1', true);
                return $handler->handle($request);
            }
        };

        $middleware2 = new class($executionOrder) implements MiddlewareInterface {
            private array $order;
            public function __construct(array &$order)
            {
                $this->order = &$order;
            }
            public function handle(Request $request, RequestHandlerInterface $handler): Response
            {
                $this->order[] = 'middleware2';
                $request = $request->withAttribute('mw2', true);
                return $handler->handle($request);
            }
        };

        $finalHandler = new class implements RequestHandlerInterface {
            public function handle(Request $request): Response
            {
                return new Response('Done', 200);
            }
        };

        $pipeline = new MiddlewarePipeline($finalHandler);
        $pipeline->pipe($middleware1);
        $pipeline->pipe($middleware2);

        $response = $pipeline->handle(new Request());

        $this->assertEquals(['middleware1', 'middleware2'], $executionOrder);
        $this->assertEquals(200, $response->getStatusCode());
    }
}
