<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\ModuleSystem;

use Hyperdrive\Application\Module;
use Hyperdrive\Application\ModuleRegistry;
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
    public function test_it_can_register_and_resolve_modules(): void
    {
        $registry = new ModuleRegistry();

        $registry->register(RegistryTestModule::class);

        $this->assertTrue($registry->has(RegistryTestModule::class));
        $this->assertEquals([RegistryTestController::class], $registry->getControllers(RegistryTestModule::class));
        $this->assertEquals([RegistryTestService::class], $registry->getInjectables(RegistryTestModule::class));
    }

    public function test_it_handles_module_imports(): void
    {
        $registry = new ModuleRegistry();

        $registry->register(RegistryParentModule::class);

        $this->assertTrue($registry->has(RegistryParentModule::class));
        $this->assertTrue($registry->has(RegistryChildModule::class));
        $this->assertEquals([RegistryParentController::class], $registry->getControllers(RegistryParentModule::class));
        $this->assertEquals([RegistryChildService::class], $registry->getInjectables(RegistryChildModule::class));
    }
}
