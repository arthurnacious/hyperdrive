<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Routing;

use Hyperdrive\Attributes\Http\Verbs\Get;
use Hyperdrive\Attributes\Http\Verbs\Post;
use Hyperdrive\Routing\Router;
use PHPUnit\Framework\TestCase;

class PlainController
{
    #[Get]
    public function index(): array
    {
        return ['message' => 'index'];
    }

    #[Get('/custom')]
    public function custom(): array
    {
        return ['message' => 'custom'];
    }

    #[Post('/create')]
    public function create(): array
    {
        return ['message' => 'created'];
    }
}

class RouterEdgeCasesTest extends TestCase
{
    public function test_it_handles_controller_without_route_attribute(): void
    {
        $router = new Router();
        $router->registerController(PlainController::class);

        $route = $router->findRoute('GET', '/');
        $this->assertNotNull($route);
        $this->assertEquals('index', $route->getMethodName());

        $route = $router->findRoute('GET', '/custom');
        $this->assertNotNull($route);
        $this->assertEquals('custom', $route->getMethodName());
    }

    public function test_it_distinguishes_between_http_methods(): void
    {
        $router = new Router();
        $router->registerController(PlainController::class);

        $getRoute = $router->findRoute('GET', '/create');
        $postRoute = $router->findRoute('POST', '/create');

        $this->assertNull($getRoute); // No GET route for /create
        $this->assertNotNull($postRoute);
        $this->assertEquals('create', $postRoute->getMethodName());
    }
}
