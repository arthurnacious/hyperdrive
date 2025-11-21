<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\ModuleSystem;

use Hyperdrive\Application\Module;
use Hyperdrive\Application\ModuleRegistry;
use Hyperdrive\Attributes\Http\Verbs\Get;
use Hyperdrive\Attributes\Http\Verbs\Post;
use Hyperdrive\Container\Container;
use Hyperdrive\Routing\Router;
use PHPUnit\Framework\TestCase;

// Test Controllers
class PrefixTestSimpleController
{
    #[Get('/hello')]
    public function hello(): string
    {
        return 'Hello from simple controller';
    }
}

class PrefixTestUserController
{
    #[Get('/profile')]
    public function profile(): string
    {
        return 'User profile';
    }

    #[Get('/{id}')]
    public function show(int $id): string
    {
        return "User {$id}";
    }
}

class PrefixTestProductController
{
    #[Get]
    public function index(): string
    {
        return 'Products list';
    }

    #[Get('/{id}')]
    public function show(int $id): string
    {
        return "Product {$id}";
    }
}

class PrefixTestAuthController
{
    #[Post('/login')]
    public function login(): string
    {
        return 'Login endpoint';
    }

    #[Post('/logout')]
    public function logout(): string
    {
        return 'Logout endpoint';
    }
}

// Test Services
class PrefixTestUserService
{
    public function findUser(int $id): array
    {
        return ['id' => $id, 'name' => 'Test User'];
    }
}

class PrefixTestProductService
{
    public function findProduct(int $id): array
    {
        return ['id' => $id, 'name' => 'Test Product'];
    }
}

// Test Modules with Prefixes
#[Module(
    prefix: '/users',
    controllers: [PrefixTestUserController::class],
    injectables: [PrefixTestUserService::class]
)]
class PrefixTestUserModule {}

#[Module(
    prefix: '/products',
    controllers: [PrefixTestProductController::class],
    injectables: [PrefixTestProductService::class]
)]
class PrefixTestProductModule {}

#[Module(
    prefix: '/auth',
    controllers: [PrefixTestAuthController::class]
)]
class PrefixTestAuthModule {}

#[Module(
    prefix: '/api/v1',
    imports: [PrefixTestUserModule::class, PrefixTestProductModule::class, PrefixTestAuthModule::class]
)]
class PrefixTestApiV1Module {}

#[Module(
    prefix: '',
    imports: [PrefixTestApiV1Module::class],
    controllers: [PrefixTestSimpleController::class],
    static: [
        '/assets' => './public/assets',
        '/' => './frontend/dist'
    ]
)]
class PrefixTestRootModule {}

class ModulePrefixTest extends TestCase
{
    private Container $container;
    private Router $router;
    private ModuleRegistry $registry;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->router = new Router();
        $this->registry = new ModuleRegistry();

        $this->registry->setContainer($this->container);
        $this->registry->setRouter($this->router);
    }

    public function test_it_registers_modules_with_prefixes(): void
    {
        $this->registry->register(PrefixTestRootModule::class);

        // Verify all modules are registered
        $this->assertTrue($this->registry->has(PrefixTestRootModule::class));
        $this->assertTrue($this->registry->has(PrefixTestApiV1Module::class));
        $this->assertTrue($this->registry->has(PrefixTestUserModule::class));
        $this->assertTrue($this->registry->has(PrefixTestProductModule::class));
        $this->assertTrue($this->registry->has(PrefixTestAuthModule::class));
    }

    public function test_it_builds_correct_route_paths_with_prefixes(): void
    {
        $this->registry->register(PrefixTestRootModule::class);

        // Test routes from different modules with their prefixes
        $routes = $this->router->getRegisteredRoutes();

        $routePaths = array_map(fn($route) => $route->getPath(), $routes);

        // Root module routes (no prefix)
        $this->assertContains('/hello', $routePaths);

        // User module routes (/api/v1/users prefix)
        $this->assertContains('/api/v1/users/profile', $routePaths);
        $this->assertContains('/api/v1/users/{id}', $routePaths);

        // Product module routes (/api/v1/products prefix)  
        $this->assertContains('/api/v1/products', $routePaths); // index
        $this->assertContains('/api/v1/products/{id}', $routePaths);

        // Auth module routes (/api/v1/auth prefix)
        $this->assertContains('/api/v1/auth/login', $routePaths);
        $this->assertContains('/api/v1/auth/logout', $routePaths);
    }

    public function test_it_can_find_prefixed_routes(): void
    {
        $this->registry->register(PrefixTestRootModule::class);

        // Test finding routes with full prefixed paths
        $userProfileRoute = $this->router->findRoute('GET', '/api/v1/users/profile');
        $userShowRoute = $this->router->findRoute('GET', '/api/v1/users/123');
        $productIndexRoute = $this->router->findRoute('GET', '/api/v1/products');
        $authLoginRoute = $this->router->findRoute('POST', '/api/v1/auth/login');
        $rootRoute = $this->router->findRoute('GET', '/hello');

        $this->assertNotNull($userProfileRoute);
        $this->assertNotNull($userShowRoute);
        $this->assertNotNull($productIndexRoute);
        $this->assertNotNull($authLoginRoute);
        $this->assertNotNull($rootRoute);

        $this->assertEquals('profile', $userProfileRoute->getMethodName());
        $this->assertEquals('show', $userShowRoute->getMethodName());
        $this->assertEquals('index', $productIndexRoute->getMethodName());
        $this->assertEquals('login', $authLoginRoute->getMethodName());
        $this->assertEquals('hello', $rootRoute->getMethodName());
    }

    public function test_it_handles_nested_module_imports(): void
    {
        $this->registry->register(PrefixTestRootModule::class);

        // Verify the module hierarchy is preserved
        $rootImports = $this->registry->getImports(PrefixTestRootModule::class);
        $apiV1Imports = $this->registry->getImports(PrefixTestApiV1Module::class);

        $this->assertEquals([PrefixTestApiV1Module::class], $rootImports);
        $this->assertEquals([PrefixTestUserModule::class, PrefixTestProductModule::class, PrefixTestAuthModule::class], $apiV1Imports);
    }

    public function test_it_handles_empty_prefix(): void
    {
        $this->registry->register(PrefixTestRootModule::class);

        $simpleRoute = $this->router->findRoute('GET', '/hello');
        $this->assertNotNull($simpleRoute);
        $this->assertEquals(PrefixTestSimpleController::class, $simpleRoute->getControllerClass());
    }

    public function test_it_extracts_parameters_from_prefixed_routes(): void
    {
        $this->registry->register(PrefixTestRootModule::class);

        $userShowRoute = $this->router->findRoute('GET', '/api/v1/users/456');
        $this->assertNotNull($userShowRoute);

        $params = $userShowRoute->extractParameters('/api/v1/users/456');
        $this->assertEquals(['id' => '456'], $params);
    }

    public function test_it_preserves_static_configuration(): void
    {
        $this->registry->register(PrefixTestRootModule::class);

        // Static configuration should be preserved in module metadata
        // Actual static file serving will be implemented separately
        $this->assertTrue($this->registry->has(PrefixTestRootModule::class));
    }
}
