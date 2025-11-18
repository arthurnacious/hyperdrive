<?php

namespace Hyperdrive\Tests;

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
}
