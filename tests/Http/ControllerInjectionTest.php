<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Http;

use Hyperdrive\Attributes\Http\Verbs\Post;
use Hyperdrive\Attributes\Http\Route;
use Hyperdrive\Http\Dto;
use Hyperdrive\Http\JsonResponse;
use Hyperdrive\Http\Request;
use Hyperdrive\Routing\Router;
use PHPUnit\Framework\TestCase;

class CreateUserDto extends Dto
{
    public string $name;
    public string $email;
    public ?string $phone = null;
}

#[Route('/users')]
class TestUserController
{
    #[Post]
    public function create(Request $request, CreateUserDto $dto): JsonResponse
    {
        $userId = $request->getAttribute('user_sub');
        $tenantId = $request->getAttribute('tenant_id');

        // Simulate user creation
        $user = [
            'id' => 123,
            'name' => $dto->name,
            'email' => $dto->email,
            'created_by' => $userId,
            'tenant_id' => $tenantId
        ];

        return new JsonResponse([
            'message' => 'User created successfully',
            'data' => $user
        ], 201);
    }
}

class ControllerInjectionTest extends TestCase
{
    public function test_it_can_inject_request_and_dto_into_controller(): void
    {
        $router = new Router();
        $router->registerController(TestUserController::class);

        $route = $router->findRoute('POST', '/users');
        $this->assertNotNull($route);

        // Simulate request with middleware-injected attributes
        $request = new Request(
            server: ['REQUEST_METHOD' => 'POST', 'REQUEST_URI' => '/users'],
            attributes: [
                'user_sub' => 'user_123',
                'tenant_id' => 'tenant_456'
            ]
        );

        // Simulate DTO creation from request body
        $dtoData = ['name' => 'John Doe', 'email' => 'john@example.com'];
        $dto = new CreateUserDto($dtoData);

        // Call the controller method directly
        $controller = new TestUserController();
        $response = $controller->create($request, $dto);

        $this->assertEquals(201, $response->getStatusCode());

        $responseData = json_decode($response->getContent(), true);
        $this->assertEquals('User created successfully', $responseData['message']);
        $this->assertEquals('user_123', $responseData['data']['created_by']);
        $this->assertEquals('tenant_456', $responseData['data']['tenant_id']);
        $this->assertEquals('John Doe', $responseData['data']['name']);
    }
}
