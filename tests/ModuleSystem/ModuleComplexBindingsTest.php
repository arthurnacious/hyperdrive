<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\ModuleSystem;

use Hyperdrive\Application\Module;
use Hyperdrive\Application\ModuleRegistry;
use Hyperdrive\Container\Container;
use PHPUnit\Framework\TestCase;

interface UserRepositoryInterface {}
class UserRepository implements UserRepositoryInterface {}

interface ThoughtRepositoryInterface {}
class ThoughtRepository implements ThoughtRepositoryInterface {}

class ThoughtService
{
    public function __construct(
        public ThoughtRepositoryInterface $thoughts,
        public UserRepositoryInterface $users
    ) {}
}

#[Module(
    injectables: [
        UserRepositoryInterface::class => UserRepository::class,
        ThoughtRepositoryInterface::class => ThoughtRepository::class,
        ThoughtService::class,
    ],
    exports: [
        ThoughtService::class,
        ThoughtRepositoryInterface::class,
    ]
)]
class ComplexBindingModule {}

class ModuleComplexBindingsTest extends TestCase
{
    public function test_it_handles_multiple_interface_bindings(): void
    {
        $registry = new ModuleRegistry();
        $container = new Container();
        $registry->setContainer($container);

        $registry->register(ComplexBindingModule::class);

        // Test interface bindings
        $userRepo = $container->get(UserRepositoryInterface::class);
        $thoughtRepo = $container->get(ThoughtRepositoryInterface::class);

        $this->assertInstanceOf(UserRepository::class, $userRepo);
        $this->assertInstanceOf(ThoughtRepository::class, $thoughtRepo);

        // Test service with injected interfaces
        $service = $container->get(ThoughtService::class);
        $this->assertInstanceOf(ThoughtRepositoryInterface::class, $service->thoughts);
        $this->assertInstanceOf(UserRepositoryInterface::class, $service->users);
        $this->assertInstanceOf(ThoughtRepository::class, $service->thoughts);
        $this->assertInstanceOf(UserRepository::class, $service->users);
    }

    public function test_it_handles_mixed_bindings_and_concrete_classes(): void
    {
        $registry = new ModuleRegistry();
        $container = new Container();
        $registry->setContainer($container);

        $registry->register(ComplexBindingModule::class);

        // Should be able to resolve both the interface and concrete
        $viaInterface = $container->get(ThoughtRepositoryInterface::class);
        $viaConcrete = $container->get(ThoughtRepository::class);

        $this->assertInstanceOf(ThoughtRepository::class, $viaInterface);
        $this->assertInstanceOf(ThoughtRepository::class, $viaConcrete);
        $this->assertSame($viaInterface, $viaConcrete); // Should be same instance (singleton)
    }
}
