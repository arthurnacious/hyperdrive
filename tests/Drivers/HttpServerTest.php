<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Drivers;

use Hyperdrive\Attributes\Http\Verbs\Get;
use Hyperdrive\Drivers\RoadstarDriver;
use Hyperdrive\Http\Request;
use Hyperdrive\Routing\Router;
use PHPUnit\Framework\TestCase;

// Make sure this is in the same namespace as the test
class TestSimpleController
{
    #[Get('/hello')]
    public function hello(): string
    {
        return 'Hello, Hyperdrive!';
    }
}

class HttpServerTest extends TestCase
{
    public function test_roadstar_can_handle_http_requests(): void
    {
        $driver = new RoadstarDriver('TestModule', 'testing');
        $router = new Router();

        // Register a test route
        $router->registerController(TestSimpleController::class);
        $driver->setRouter($router);
        $driver->boot();

        $request = new Request(
            server: [
                'REQUEST_METHOD' => 'GET',
                'REQUEST_URI' => '/hello'
            ]
        );

        // Debug the router directly
        $foundRoute = $router->findRoute('GET', '/hello');

        $response = $driver->handleRequest($request);

        echo "Response status: {$response->getStatusCode()}\n";
        echo "Response content: {$response->getContent()}\n";

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertStringContainsString('Roadstar: Handled GET /hello', $response->getContent());
    }
}
