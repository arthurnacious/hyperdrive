<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Routing;

use Hyperdrive\Attributes\Http\Verbs\Get;
use Hyperdrive\Attributes\Middleware;
use Hyperdrive\Routing\Router;
use PHPUnit\Framework\TestCase;

// Test controller classes
#[Middleware(middlewares: ['ControllerMiddleware'])]
class TestControllerWithClassMiddleware
{
    #[Get('/test')]
    public function index() {}
}

class TestControllerWithMethodMiddleware
{
    #[Middleware(middlewares: ['MethodMiddleware'])]
    #[Get('/test')]
    public function index() {}
}

#[Middleware(middlewares: ['ControllerMiddleware1'])]
class TestControllerWithBoth
{
    #[Middleware(middlewares: ['MethodMiddleware1', 'MethodMiddleware2'])]
    #[Get('/test')]
    public function index() {}
}

class RouterMiddlewareTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function test_router_collects_controller_class_middlewares(): void
    {
        $this->router->registerController(TestControllerWithClassMiddleware::class, '');

        $route = $this->router->findRoute('GET', '/test');

        $this->assertNotNull($route);
        $this->assertEquals(['ControllerMiddleware'], $route->getMiddlewares());
    }

    public function test_router_collects_method_middlewares(): void
    {
        $this->router->registerController(TestControllerWithMethodMiddleware::class, '');

        $route = $this->router->findRoute('GET', '/test');

        $this->assertNotNull($route);
        $this->assertEquals(['MethodMiddleware'], $route->getMiddlewares());
    }

    public function test_router_merges_controller_and_method_middlewares(): void
    {
        $this->router->registerController(TestControllerWithBoth::class, '');

        $route = $this->router->findRoute('GET', '/test');

        $this->assertNotNull($route);
        $this->assertEquals([
            'ControllerMiddleware1',
            'MethodMiddleware1',
            'MethodMiddleware2'
        ], $route->getMiddlewares());
    }

    public function test_router_removes_duplicate_middlewares(): void
    {
        // Create controller with SINGLE Middleware attribute containing duplicates
        $controller = new class {
            #[Middleware(middlewares: ['AuthMiddleware', 'AuthMiddleware', 'AdminMiddleware'])] // Duplicates IN ARRAY
            #[Get('/test')]
            public function index() {}
        };

        $className = get_class($controller);

        // Test the router directly
        $this->router->registerController($className, '');

        $route = $this->router->findRoute('GET', '/test');

        $this->assertNotNull($route);
        $this->assertEquals(['AuthMiddleware', 'AdminMiddleware'], $route->getMiddlewares());

        // Also test the hasMiddleware method
        $this->assertTrue($route->hasMiddleware('AuthMiddleware'));
        $this->assertTrue($route->hasMiddleware('AdminMiddleware'));
        $this->assertFalse($route->hasMiddleware('NonExistentMiddleware'));
    }

    public function test_route_has_middleware_method(): void
    {
        $this->router->registerController(TestControllerWithClassMiddleware::class, '');

        $route = $this->router->findRoute('GET', '/test');

        $this->assertTrue($route->hasMiddleware('ControllerMiddleware'));
        $this->assertFalse($route->hasMiddleware('NonExistentMiddleware'));
    }
}
