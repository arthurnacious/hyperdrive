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
}
