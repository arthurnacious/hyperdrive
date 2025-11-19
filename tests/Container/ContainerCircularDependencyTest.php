<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Container;

use Hyperdrive\Container\Container;
use Hyperdrive\Container\ContainerException;
use PHPUnit\Framework\TestCase;

class ServiceA
{
    public function __construct(public ServiceB $b) {}
}

class ServiceB
{
    public function __construct(public ServiceA $a) {}
}

class ContainerCircularDependencyTest extends TestCase
{
    public function test_it_detects_circular_dependencies(): void
    {
        $container = new Container();

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Circular dependency detected');

        $container->get(ServiceA::class);
    }

    public function test_it_handles_self_referencing_class(): void
    {
        $container = new Container();

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Circular dependency detected');

        $container->get(ServiceA::class);
    }
}
