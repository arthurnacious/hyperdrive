<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Routing;

use Hyperdrive\Attributes\Http\Verbs\Get;
use Hyperdrive\Attributes\Http\Route;
use Hyperdrive\Routing\Router;
use PHPUnit\Framework\TestCase;

#[Route('/users')]
class TestUserController
{
    #[Get]
    public function index(): array
    {
        return ['users' => []];
    }

    #[Get('/{id}')]
    public function show(int $id): array
    {
        return ['user' => ['id' => $id]];
    }
}

class RouterTest extends TestCase
{
    public function test_it_can_register_controllers_and_find_routes(): void
    {
        $router = new Router();
        $router->registerController(TestUserController::class);

        $route = $router->findRoute('GET', '/users');
        $this->assertNotNull($route);
        $this->assertEquals('index', $route->getMethodName());

        $route = $router->findRoute('GET', '/users/123');
        $this->assertNotNull($route);
        $this->assertEquals('show', $route->getMethodName());
    }
}
