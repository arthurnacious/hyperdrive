<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Http\Middleware;

use Hyperdrive\Attributes\Http\Route;
use Hyperdrive\Attributes\Http\Verbs\Get;
use Hyperdrive\Attributes\Middleware;
use Hyperdrive\Drivers\RoadstarDriver;
use Hyperdrive\Http\Middleware\MiddlewareInterface;
use Hyperdrive\Http\Middleware\RequestHandlerInterface;
use Hyperdrive\Http\Request;
use Hyperdrive\Http\Response;
use Hyperdrive\Routing\Router;
use PHPUnit\Framework\TestCase;

class FailClosedBrokenGuard implements MiddlewareInterface
{
    public function __construct()
    {
        throw new \RuntimeException('guard could not start');
    }

    public function handle(Request $request, RequestHandlerInterface $next): Response
    {
        return new Response('denied', 403);
    }
}

class FailClosedNotAMiddleware {}

#[Route('/secret')]
class FailClosedController
{
    #[Get('/broken')]
    #[Middleware([FailClosedBrokenGuard::class])]
    public function broken(): string
    {
        return 'secret data';
    }

    #[Get('/missing')]
    #[Middleware(['App\\Does\\Not\\Exist'])]
    public function missing(): string
    {
        return 'secret data';
    }

    #[Get('/wrong-type')]
    #[Middleware([FailClosedNotAMiddleware::class])]
    public function wrongType(): string
    {
        return 'secret data';
    }
}

class RouteMiddlewareFailClosedTest extends TestCase
{
    private function get(string $path): Response
    {
        $router = new Router();
        $router->registerController(FailClosedController::class);

        $driver = new RoadstarDriver(FailClosedController::class, 'production');
        $driver->setRouter($router);
        $driver->boot();

        return $driver->handleRequest(new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $path]));
    }

    public function test_route_does_not_run_when_its_middleware_fails_to_construct(): void
    {
        $response = $this->get('/secret/broken');

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringNotContainsString('secret data', $response->getContent());
    }

    public function test_route_does_not_run_when_its_middleware_class_is_missing(): void
    {
        $response = $this->get('/secret/missing');

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringNotContainsString('secret data', $response->getContent());
    }

    public function test_route_does_not_run_when_its_middleware_is_not_a_middleware(): void
    {
        $response = $this->get('/secret/wrong-type');

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringNotContainsString('secret data', $response->getContent());
    }
}
