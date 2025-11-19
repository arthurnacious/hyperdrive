<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Container;

use Hyperdrive\Container\Container;
use PHPUnit\Framework\TestCase;

class DependencyA {}
class DependencyB {}

class ServiceWithDependencies
{
    public function __construct(
        public DependencyA $a,
        public DependencyB $b
    ) {}
}

class ContainerDependenciesTest extends TestCase
{
    public function test_it_resolves_nested_dependencies(): void
    {
        $container = new Container();

        $service = $container->get(ServiceWithDependencies::class);

        $this->assertInstanceOf(ServiceWithDependencies::class, $service);
        $this->assertInstanceOf(DependencyA::class, $service->a);
        $this->assertInstanceOf(DependencyB::class, $service->b);
    }
}
