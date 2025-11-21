<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Drivers;

use Hyperdrive\Attributes\Http\Verbs\Get;
use Hyperdrive\Attributes\Http\Route;
use Hyperdrive\Drivers\RoadstarDriver;
use Hyperdrive\Http\Request;
use Hyperdrive\Routing\Router;
use PHPUnit\Framework\TestCase;

#[Route('/users')]
class TestUserController
{
    #[Get]
    public function index(): string
    {
        return 'Users list';
    }
}

class RoadstarHttpTest extends TestCase
{
    public function test_it_handles_http_requests(): void
    {
        $driver = new RoadstarDriver('TestModule', 'testing');
        $router = new Router();

        // Register a test route
        $router->registerController(TestUserController::class);
        $driver->setRouter($router);
        $driver->boot();

        $request = new Request(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/users'
            ]
        );

        $response = $driver->handleRequest($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Roadstar: Handled GET /users', $response->getContent());
    }

    public function test_it_returns_404_for_unknown_routes(): void
    {
        $driver = new RoadstarDriver(TestModule::class, 'testing');
        $router = new Router();
        $driver->setRouter($router);
        $driver->boot();

        $request = new Request(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/unknown-route'
            ]
        );

        $response = $driver->handleRequest($request);

        $this->assertEquals(404, $response->getStatusCode());
    }

    public function test_it_handles_router_injection(): void
    {
        $driver = new RoadstarDriver(TestModule::class, 'testing');
        $router = new Router();
        $driver->setRouter($router);
        $driver->boot();

        $this->assertTrue($driver->isRunning());
    }
}
