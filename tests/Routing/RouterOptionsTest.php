<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Routing;

use Hyperdrive\Attributes\Http\Verbs\Options;
use Hyperdrive\Attributes\Http\Verbs\Post;
use Hyperdrive\Routing\Router;
use PHPUnit\Framework\TestCase;

class TestOptionsController
{
    #[Options('/test')]
    public function options() {}

    #[Post('/test')]
    public function post() {}
}

class RouterOptionsTest extends TestCase
{
    private Router $router;

    protected function setUp(): void
    {
        $this->router = new Router();
    }

    public function test_router_registers_options_method(): void
    {
        $this->router->registerController(TestOptionsController::class, '');

        $optionsRoute = $this->router->findRoute('OPTIONS', '/test');
        $postRoute = $this->router->findRoute('POST', '/test');

        $this->assertNotNull($optionsRoute);
        $this->assertEquals('OPTIONS', $optionsRoute->getMethod());

        $this->assertNotNull($postRoute);
        $this->assertEquals('POST', $postRoute->getMethod());
    }
}
