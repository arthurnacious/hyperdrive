<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\ModuleSystem;

use Hyperdrive\Application\Module;
use Hyperdrive\Application\ModuleRegistry;
use Hyperdrive\Container\Container;
use PHPUnit\Framework\TestCase;

class RegistryTestController {}
class RegistryTestService {}
class RegistryChildService {}
class RegistryParentController {}

#[Module(injectables: [RegistryChildService::class])]
class RegistryChildModule {}

#[Module(
    imports: [RegistryChildModule::class],
    controllers: [RegistryParentController::class]
)]
class RegistryParentModule {}

#[Module(
    controllers: [RegistryTestController::class],
    injectables: [RegistryTestService::class]
)]
class RegistryTestModule {}

class ModuleRegistryTest extends TestCase
{
    private Container $container;
    private ModuleRegistry $registry;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->registry = new ModuleRegistry();
        $this->registry->setContainer($this->container);
    }

    public function test_it_can_register_and_resolve_modules(): void
    {
        $this->registry->register(RegistryTestModule::class);

        $this->assertTrue($this->registry->has(RegistryTestModule::class));
        $this->assertEquals([RegistryTestController::class], $this->registry->getControllers(RegistryTestModule::class));
        $this->assertEquals([RegistryTestService::class], $this->registry->getInjectables(RegistryTestModule::class));
    }

    public function test_it_handles_module_imports(): void
    {
        $this->registry->register(RegistryParentModule::class);

        $this->assertTrue($this->registry->has(RegistryParentModule::class));
        $this->assertTrue($this->registry->has(RegistryChildModule::class));
        $this->assertEquals([RegistryParentController::class], $this->registry->getControllers(RegistryParentModule::class));
    }
}
