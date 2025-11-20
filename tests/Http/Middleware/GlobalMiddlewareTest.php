<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Http\Middleware;

use Hyperdrive\Config\Config;
use Hyperdrive\Http\Middleware\MiddlewareInterface;
use Hyperdrive\Http\Middleware\RequestHandlerInterface;
use Hyperdrive\Http\Request;
use Hyperdrive\Http\Response;
use PHPUnit\Framework\TestCase;

class TestGlobalMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, RequestHandlerInterface $handler): Response
    {
        $request = $request->withAttribute('global_middleware_applied', true);
        return $handler->handle($request);
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
}
