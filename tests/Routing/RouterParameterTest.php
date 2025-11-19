<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Routing;

use Hyperdrive\Attributes\Http\Route;
use Hyperdrive\Attributes\Http\Verbs\Get;
use Hyperdrive\Routing\Router;
use PHPUnit\Framework\TestCase;

#[Route('/api')]
class TestApiController
{
    #[Get('/users/{id}')]
    public function getUser(int $id): array
    {
        return ['user_id' => $id];
    }

    #[Get('/posts/{postId}/comments/{commentId}')]
    public function getComment(int $postId, int $commentId): array
    {
        return ['post_id' => $postId, 'comment_id' => $commentId];
    }
}

class RouterParameterTest extends TestCase
{
    public function test_it_extracts_route_parameters(): void
    {
        $router = new Router();
        $router->registerController(TestApiController::class);

        $route = $router->findRoute('GET', '/api/users/123');
        $this->assertNotNull($route);

        $params = $route->extractParameters('/api/users/123');
        $this->assertEquals(['id' => '123'], $params);
    }

    public function test_it_extracts_multiple_parameters(): void
    {
        $router = new Router();
        $router->registerController(TestApiController::class);

        $route = $router->findRoute('GET', '/api/posts/456/comments/789');
        $this->assertNotNull($route);

        $params = $route->extractParameters('/api/posts/456/comments/789');
        $this->assertEquals(['postId' => '456', 'commentId' => '789'], $params);
    }
}
