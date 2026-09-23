<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Container;

use Hyperdrive\Container\Container;
use PHPUnit\Framework\TestCase;

interface TestInterface {}
class TestImplementation implements TestInterface {}

class ContainerBindingsTest extends TestCase
{
    public function test_it_can_bind_interface_to_implementation(): void
    {
        $container = new Container();
        $container->bind(TestInterface::class, TestImplementation::class);

        $instance = $container->get(TestInterface::class);

        $this->assertInstanceOf(TestImplementation::class, $instance);
    }

    public function test_instance_registers_an_already_built_object_as_the_resolved_singleton(): void
    {
        $container = new Container();
        $preBuilt = new TestImplementation();

        $container->instance(TestImplementation::class, $preBuilt);

        $this->assertSame($preBuilt, $container->get(TestImplementation::class));
    }

    public function test_instance_can_seed_an_interface_id_too(): void
    {
        $container = new Container();
        $preBuilt = new TestImplementation();

        $container->instance(TestInterface::class, $preBuilt);

        $this->assertSame($preBuilt, $container->get(TestInterface::class));
    }
}
