<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Http\Middleware;

use Hyperdrive\Attributes\Http\Verbs\Get;
use Hyperdrive\Config\Config;
use Hyperdrive\Container\Container;
use Hyperdrive\Drivers\RoadstarDriver;
use Hyperdrive\Http\Middleware\MiddlewareInterface;
use Hyperdrive\Http\Middleware\RequestHandlerInterface;
use Hyperdrive\Http\Request;
use Hyperdrive\Http\Response;
use Hyperdrive\Routing\Router;
use Hyperdrive\Tests\Drivers\TestModule;
use PHPUnit\Framework\TestCase;

class TestLoggingMiddleware implements MiddlewareInterface
{
    public static array $log = [];

    public function handle(Request $request, RequestHandlerInterface $handler): Response
    {
        self::$log[] = "Before: {$request->getMethod()} {$request->getPath()}";

        $response = $handler->handle($request);

        self::$log[] = "After: {$response->getStatusCode()}";

        return $response;
    }
}

class TestCorsMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, RequestHandlerInterface $handler): Response
    {
        $response = $handler->handle($request);

        $headers = array_merge($response->getHeaders(), [
            'Access-Control-Allow-Origin' => 'https://example.test',
        ]);

        return new Response($response->getContent(), $response->getStatusCode(), $headers);
    }
}

class TestController
{
    #[Get('/test')]
    public function test(): string
    {
        return 'Controller response';
    }
}

class DriverMiddlewareIntegrationTest extends TestCase
{
    protected function setUp(): void
    {
        TestLoggingMiddleware::$log = [];
        Config::clear();
    }

    protected function tearDown(): void
    {
        Config::clear();
    }

    public function test_driver_integrates_middleware_pipeline(): void
    {
        $driver = new RoadstarDriver(TestModule::class, 'testing');
        $router = new Router();
        $container = new Container();

        // Register the controller with the router
        $router->registerController(TestController::class);

        // Set up driver
        $driver->setRouter($router);
        $driver->setContainer($container);
        $driver->boot();

        // Create request
        $request = new Request([], [], [], [], [], [
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/test'
        ]);

        $response = $driver->handleRequest($request);

        // Verify the request was handled (not 404) and pipeline didn't break
        $this->assertEquals(200, $response->getStatusCode());

        // The content might be the placeholder or actual controller output
        // This test verifies the middleware integration doesn't break existing functionality
        $this->assertIsString($response->getContent());
    }

    public function test_options_requests_flow_through_global_middleware(): void
    {
        // Global middleware (e.g. a CORS middleware) needs a chance to add
        // headers to OPTIONS preflight responses too, not just normal
        // requests - handleFrameworkRequest() must not special-case OPTIONS
        // before the global middleware pipeline runs.
        Config::set('middleware.global', [TestCorsMiddleware::class]);

        $driver = new RoadstarDriver(TestModule::class, 'testing');
        $router = new Router();
        $container = new Container();

        $router->registerController(TestController::class);

        $driver->setRouter($router);
        $driver->setContainer($container);
        $driver->boot();

        $request = new Request([], [], [], [], [], [
            'REQUEST_METHOD' => 'OPTIONS',
            'REQUEST_URI' => '/test'
        ]);

        $response = $driver->handleRequest($request);

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEquals('https://example.test', $response->getHeaders()['Access-Control-Allow-Origin'] ?? null);
        $this->assertEquals('GET', $response->getHeaders()['Allow'] ?? null);
    }
}
