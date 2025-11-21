<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Routing;

use Hyperdrive\Attributes\Http\Verbs\Get;
use Hyperdrive\Attributes\Http\Verbs\Post;
use Hyperdrive\Routing\Router;
use PHPUnit\Framework\TestCase;

class DebugController
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

class RouterDebugTest extends TestCase
{
    public function test_debug_routes(): void
    {
        $router = new Router();
        $router->registerController(DebugController::class);

        $routes = $router->getRegisteredRoutes();

        echo "Registered routes:\n";
        // foreach ($routes as $route) {
        //     echo " - {$route->getMethod()} {$route->getPath()} -> {$route->getControllerClass()}::{$route->getMethodName()}\n";
        // }

        // Test finding the routes
        $indexRoute = $router->findRoute('GET', '/');
        $customRoute = $router->findRoute('GET', '/custom');
        $createRoute = $router->findRoute('POST', '/create');

        // echo "\nFound routes:\n";
        // echo "GET / -> " . ($indexRoute ? $indexRoute->getMethodName() : 'NULL') . "\n";
        // echo "GET /custom -> " . ($customRoute ? $customRoute->getMethodName() : 'NULL') . "\n";
        // echo "POST /create -> " . ($createRoute ? $createRoute->getMethodName() : 'NULL') . "\n";

        $this->assertNotNull($indexRoute);
        $this->assertNotNull($customRoute);
        $this->assertNotNull($createRoute);
    }
}
