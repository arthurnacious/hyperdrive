<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Http;

use Hyperdrive\Attributes\Http\Verbs\Get;
use Hyperdrive\Attributes\Http\Verbs\Post;
use Hyperdrive\Attributes\Http\Route;
use Hyperdrive\Container\Container;
use Hyperdrive\Http\Controller;
use Hyperdrive\Http\ControllerDispatcher;
use Hyperdrive\Http\JsonResponse;
use Hyperdrive\Http\Request;
use Hyperdrive\Routing\RouteDefinition;
use PHPUnit\Framework\TestCase;

class DispatcherTestUserService
{
    public function find(int $id): array
    {
        return ['id' => $id, 'name' => 'Test User'];
    }
}

class DispatcherCreateUserDto extends \Hyperdrive\Http\Dto
{
    public string $name;
    public string $email;
}

#[Route('/api/users')]
class DispatcherTestUserController extends Controller
{
    public function __construct(private DispatcherTestUserService $userService) {}

    #[Get('/{id}')]
    public function show(int $id, Request $request): JsonResponse
    {
        $user = $this->userService->find($id);
        $authId = $request->getAttribute('user_id', 'anonymous');

        return $this->ok([
            'user' => $user,
            'requested_by' => $authId
        ]);
    }

    #[Post]
    public function store(DispatcherCreateUserDto $dto, Request $request): JsonResponse
    {
        $userId = $request->getAttribute('user_sub');
        $tenantId = $request->getAttribute('tenant_id');

        return $this->created([
            'user' => ['name' => $dto->name, 'email' => $dto->email],
            'created_by' => $userId,
            'tenant' => $tenantId
        ]);
    }

    #[Get('/simple')]
    public function simple(): string
    {
        return 'Simple response';
    }

    #[Get('/array')]
    public function arrayResponse(): array
    {
        return ['status' => 'ok'];
    }
}

class ControllerDispatcherTest extends TestCase
{
    public function test_it_dispatches_controller_with_mixed_parameters(): void
    {
        $container = new Container();
        $dispatcher = new ControllerDispatcher($container);

        // Route definition with PATTERN (not concrete path)
        $route = new RouteDefinition('GET', '/api/users/{id}', DispatcherTestUserController::class, 'show');
        // Request with concrete path that matches the pattern
        $request = new Request(
            server: ['REQUEST_URI' => '/api/users/123'],
            attributes: ['user_id' => 'auth_123']
        );

        $result = $dispatcher->dispatch($route, $request);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(200, $result->getStatusCode());

        $data = json_decode($result->getContent(), true);
        $this->assertEquals(123, $data['user']['id']);
        $this->assertEquals('auth_123', $data['requested_by']);
    }

    public function test_it_handles_dto_and_request_together(): void
    {
        $container = new Container();
        // Don't bind the service - let the container resolve it normally
        // $container->bind(DispatcherTestUserService::class, DispatcherTestUserService::class);

        $dispatcher = new ControllerDispatcher($container);

        $route = new RouteDefinition('POST', '/api/users', DispatcherTestUserController::class, 'store');
        $request = new Request(
            server: ['CONTENT_TYPE' => 'application/json'],
            content: json_encode(['name' => 'John', 'email' => 'john@example.com']),
            attributes: ['user_sub' => 'user_123', 'tenant_id' => 'tenant_456']
        );

        $result = $dispatcher->dispatch($route, $request);

        $this->assertInstanceOf(JsonResponse::class, $result);
        $this->assertEquals(201, $result->getStatusCode());

        $data = json_decode($result->getContent(), true);
        $this->assertEquals('John', $data['user']['name']);
        $this->assertEquals('user_123', $data['created_by']);
        $this->assertEquals('tenant_456', $data['tenant']);
    }
}
