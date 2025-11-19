<?php

declare(strict_types=1);

namespace Hyperdrive\Tests;

use Hyperdrive\Attributes\Http\Route;
use Hyperdrive\Attributes\Http\Verbs\Delete;
use Hyperdrive\Attributes\Http\Verbs\Get;
use Hyperdrive\Attributes\Http\Verbs\Patch;
use Hyperdrive\Attributes\Http\Verbs\Post;
use Hyperdrive\Attributes\Http\Verbs\Put;
use PHPUnit\Framework\TestCase;

/**
 * @test
 */
class ArchitectureTest extends TestCase
{
    public function test_module_attribute_is_final(): void
    {
        $reflection = new \ReflectionClass(\Hyperdrive\Application\Module::class);
        $this->assertTrue($reflection->isFinal());
    }

    public function test_module_registry_is_not_final(): void
    {
        $reflection = new \ReflectionClass(\Hyperdrive\Application\ModuleRegistry::class);
        $this->assertFalse($reflection->isFinal());
    }

    public function test_module_has_correct_target(): void
    {
        $reflection = new \ReflectionClass(\Hyperdrive\Application\Module::class);
        $attributes = $reflection->getAttributes(\Attribute::class);

        $attribute = $attributes[0]->newInstance();
        $this->assertEquals(\Attribute::TARGET_CLASS, $attribute->flags);
    }

    public function test_container_is_not_final(): void
    {
        $reflection = new \ReflectionClass(\Hyperdrive\Container\Container::class);
        $this->assertFalse($reflection->isFinal());
    }

    public function test_container_exception_extends_base_exception(): void
    {
        $reflection = new \ReflectionClass(\Hyperdrive\Container\ContainerException::class);
        $this->assertTrue($reflection->isSubclassOf(\Exception::class));
    }

    public function test_http_verb_attributes_are_final(): void
    {
        $verbs = [
            Get::class,
            Post::class,
            Put::class,
            Delete::class,
            Patch::class,
            Route::class,
        ];

        foreach ($verbs as $verb) {
            $reflection = new \ReflectionClass($verb);
            $this->assertTrue($reflection->isFinal(), "{$verb} should be final");
        }
    }

    public function test_http_verb_attributes_have_method_target(): void
    {
        $verbs = [
            Get::class,
            Post::class,
            Put::class,
            Delete::class,
            Patch::class,
        ];

        foreach ($verbs as $verb) {
            $reflection = new \ReflectionClass($verb);
            $attributes = $reflection->getAttributes(\Attribute::class);
            $attribute = $attributes[0]->newInstance();
            $this->assertEquals(\Attribute::TARGET_METHOD, $attribute->flags, "{$verb} should target methods");
        }
    }

    public function test_route_attribute_has_class_target(): void
    {
        $reflection = new \ReflectionClass(\Hyperdrive\Attributes\Http\Route::class);
        $attributes = $reflection->getAttributes(\Attribute::class);
        $attribute = $attributes[0]->newInstance();
        $this->assertEquals(\Attribute::TARGET_CLASS, $attribute->flags);
    }

    public function test_router_is_not_final(): void
    {
        $reflection = new \ReflectionClass(\Hyperdrive\Routing\Router::class);
        $this->assertFalse($reflection->isFinal());
    }

    public function test_route_definition_is_not_final(): void
    {
        $reflection = new \ReflectionClass(\Hyperdrive\Routing\RouteDefinition::class);
        $this->assertFalse($reflection->isFinal());
    }

    public function test_config_is_singleton(): void
    {
        $reflection = new \ReflectionClass(\Hyperdrive\Config\Config::class);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        $hasGetInstance = false;
        foreach ($methods as $method) {
            if ($method->isStatic() && $method->getName() === 'getInstance') {
                $hasGetInstance = true;
                break;
            }
        }

        $this->assertTrue($hasGetInstance, 'Config should have getInstance method for singleton pattern');
    }

    public function test_environment_methods_are_static(): void
    {
        $reflection = new \ReflectionClass(\Hyperdrive\Config\Environment::class);
        $methods = $reflection->getMethods(\ReflectionMethod::IS_PUBLIC);

        foreach ($methods as $method) {
            $this->assertTrue($method->isStatic(), "Environment::{$method->getName()} should be static");
        }
    }

    public function test_request_is_immutable(): void
    {
        $reflection = new \ReflectionClass(\Hyperdrive\Http\Request::class);

        // Check that withAttribute returns new instance
        $methods = $reflection->getMethods();
        $hasWithAttribute = false;

        foreach ($methods as $method) {
            if ($method->getName() === 'withAttribute') {
                $returnType = $method->getReturnType();
                $this->assertEquals('self', $returnType?->getName());
                $hasWithAttribute = true;
            }
        }

        $this->assertTrue($hasWithAttribute);
    }

    public function test_dto_is_abstract(): void
    {
        $reflection = new \ReflectionClass(\Hyperdrive\Http\Dto::class);
        $this->assertTrue($reflection->isAbstract());
    }

    public function test_json_response_extends_response(): void
    {
        $reflection = new \ReflectionClass(\Hyperdrive\Http\JsonResponse::class);
        $this->assertTrue($reflection->isSubclassOf(\Hyperdrive\Http\Response::class));
    }

    public function test_websocket_attributes_are_final(): void
    {
        $attributes = [
            \Hyperdrive\Attributes\WebSocket\WebSocketGateway::class,
            \Hyperdrive\Attributes\WebSocket\OnConnection::class,
            \Hyperdrive\Attributes\WebSocket\OnMessage::class,
            \Hyperdrive\Attributes\WebSocket\OnDisconnection::class,
        ];

        foreach ($attributes as $attribute) {
            $reflection = new \ReflectionClass($attribute);
            $this->assertTrue($reflection->isFinal(), "{$attribute} should be final");
        }
    }

    public function test_websocket_connection_is_interface(): void
    {
        $reflection = new \ReflectionClass(\Hyperdrive\WebSocket\WebSocketConnection::class);
        $this->assertTrue($reflection->isInterface());
    }

    public function test_websocket_registry_is_not_final(): void
    {
        $reflection = new \ReflectionClass(\Hyperdrive\WebSocket\WebSocketRegistry::class);
        $this->assertFalse($reflection->isFinal());
    }
}
