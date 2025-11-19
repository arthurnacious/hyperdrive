<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Container;

use Hyperdrive\Container\Container;
use Hyperdrive\Container\ContainerException;
use PHPUnit\Framework\TestCase;

class ContainerEdgeCasesTest extends TestCase
{
    public function test_it_throws_exception_for_nonexistent_class(): void
    {
        $container = new Container();

        $this->expectException(ContainerException::class);
        $container->get('NonExistentClass');
    }

    public function test_it_returns_same_instance_for_singletons(): void
    {
        $container = new Container();

        $instance1 = $container->get(ContainerTestService::class);
        $instance2 = $container->get(ContainerTestService::class);

        $this->assertSame($instance1, $instance2);
    }

    public function test_singleton_method_creates_singleton(): void
    {
        $container = new Container();
        $container->singleton(TestInterface::class, TestImplementation::class);

        $instance1 = $container->get(TestInterface::class);
        $instance2 = $container->get(TestInterface::class);

        $this->assertSame($instance1, $instance2);
    }
}
