<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\ModuleSystem;

use Hyperdrive\Application\Module;
use Hyperdrive\Application\ModuleRegistry;
use Hyperdrive\Container\Container;
use PHPUnit\Framework\TestCase;

interface TestRepositoryInterface {}
class TestRepository implements TestRepositoryInterface {}
class TestService
{
    public function __construct(public TestRepositoryInterface $repository) {}
}

#[Module(
    injectables: [
        TestRepositoryInterface::class => TestRepository::class,
        TestService::class,
    ]
)]
class TestModuleWithInterfaceBinding {}

class ModuleInterfaceBindingTest extends TestCase
{
    public function test_it_can_bind_interfaces_to_implementations_in_module(): void
    {
        $registry = new ModuleRegistry();
        $container = new Container();
        $registry->setContainer($container);

        // Register the module FIRST to set up the bindings
        $registry->register(TestModuleWithInterfaceBinding::class);

        // NOW we can resolve from the container
        $instance = $container->get(TestRepositoryInterface::class);
        $this->assertInstanceOf(TestRepository::class, $instance);

        $service = $container->get(TestService::class);
        $this->assertInstanceOf(TestRepositoryInterface::class, $service->repository);
        $this->assertInstanceOf(TestRepository::class, $service->repository);
    }
}
