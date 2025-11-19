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
}
