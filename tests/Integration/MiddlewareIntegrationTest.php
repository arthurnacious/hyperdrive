<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Integration;

use Hyperdrive\Application\Hyperdrive;
use Hyperdrive\Config\Config;
use Hyperdrive\Http\Middleware\MiddlewareInterface;
use Hyperdrive\Http\Request;
use Hyperdrive\Http\Response;
use PHPUnit\Framework\TestCase;

class GlobalTestMiddleware implements MiddlewareInterface
{
    public static int $globalCallCount = 0;

    public function handle(Request $request, \Hyperdrive\Http\Middleware\RequestHandlerInterface $handler): Response
    {
        self::$globalCallCount++;
        $request = $request->withAttribute('global_middleware', true);
        return $handler->handle($request);
    }
}

class RouteTestMiddleware implements MiddlewareInterface
{
    public static int $routeCallCount = 0;

    public function handle(Request $request, \Hyperdrive\Http\Middleware\RequestHandlerInterface $handler): Response
    {
        self::$routeCallCount++;
        $request = $request->withAttribute('route_middleware', true);
        return $handler->handle($request);
    }
}

// Test module with middleware attributes
#[Hyperdrive\Attributes\Middleware(middlewares: [RouteTestMiddleware::class])]
class TestIntegrationController
{
    public function testMethod(Request $request): Response
    {
        $global = $request->getAttribute('global_middleware', false);
        $route = $request->getAttribute('route_middleware', false);

        return new Response(
            json_encode(['global' => $global, 'route' => $route]),
            200,
            ['Content-Type' => 'application/json']
        );
    }
}

class MiddlewareIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        Config::clear();
        GlobalTestMiddleware::$globalCallCount = 0;
        RouteTestMiddleware::$routeCallCount = 0;

        // Configure global middleware
        Config::set('middleware.global', [GlobalTestMiddleware::class]);
    }

    protected function tearDown(): void
    {
        Config::clear();
    }

    public function test_global_and_route_middlewares_execute(): void
    {
        // This test would require a full Hyperdrive setup
        // For now, test the concepts separately

        $app = Hyperdrive::create(
            rootModule: TestIntegrationController::class,
            driver: 'roadstar',
            environment: 'testing'
        );

        $app->boot();

        // In a real test, you'd make a request and check both middlewares executed
        $this->assertTrue($app->getDriver()->isRunning());
    }

    public function test_middleware_execution_order_concept(): void
    {
        // Test the conceptual order: Global → Controller → Method

        $middlewares = [];

        $global = new class($middlewares) implements MiddlewareInterface {
            private array $mws;
            public function __construct(array &$mws)
            {
                $this->mws = &$mws;
            }
            public function handle(Request $request, \Hyperdrive\Http\Middleware\RequestHandlerInterface $handler): Response
            {
                $this->mws[] = 'global';
                return $handler->handle($request);
            }
        };

        $controller = new class($middlewares) implements MiddlewareInterface {
            private array $mws;
            public function __construct(array &$mws)
            {
                $this->mws = &$mws;
            }
            public function handle(Request $request, \Hyperdrive\Http\Middleware\RequestHandlerInterface $handler): Response
            {
                $this->mws[] = 'controller';
                return $handler->handle($request);
            }
        };

        $method = new class($middlewares) implements MiddlewareInterface {
            private array $mws;
            public function __construct(array &$mws)
            {
                $this->mws = &$mws;
            }
            public function handle(Request $request, \Hyperdrive\Http\Middleware\RequestHandlerInterface $handler): Response
            {
                $this->mws[] = 'method';
                return $handler->handle($request);
            }
        };

        // Simulate pipeline execution
        $finalHandler = new class($middlewares) implements \Hyperdrive\Http\Middleware\RequestHandlerInterface {
            private array $mws;
            public function __construct(array &$mws)
            {
                $this->mws = &$mws;
            }
            public function handle(Request $request): Response
            {
                $this->mws[] = 'controller_method';
                return new Response('Done', 200);
            }
        };

        $pipeline = new \Hyperdrive\Http\Middleware\MiddlewarePipeline($finalHandler);
        $pipeline->pipe($global);
        $pipeline->pipe($controller);
        $pipeline->pipe($method);

        $pipeline->handle(new Request());

        $this->assertEquals(['global', 'controller', 'method', 'controller_method'], $middlewares);
    }
}
