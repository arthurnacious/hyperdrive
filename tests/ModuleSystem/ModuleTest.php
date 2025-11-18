<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\ModuleSystem;

use Hyperdrive\Application\Module;
use PHPUnit\Framework\TestCase;

class ModuleTestModuleB {}
class ModuleTestController {}
class ModuleTestService {}

#[Module(
    imports: [ModuleTestModuleB::class],
    controllers: [ModuleTestController::class],
    injectables: [ModuleTestService::class],
    exports: [ModuleTestService::class]
)]
class ModuleTestModuleA {}

class ModuleTest extends TestCase
{
    public function test_it_can_create_module_with_attributes(): void
    {
        $reflection = new \ReflectionClass(ModuleTestModuleA::class);
        $attributes = $reflection->getAttributes(Module::class);

        $this->assertCount(1, $attributes);

        $moduleAttribute = $attributes[0]->newInstance();

        $this->assertEquals([ModuleTestModuleB::class], $moduleAttribute->imports);
        $this->assertEquals([ModuleTestController::class], $moduleAttribute->controllers);
        $this->assertEquals([ModuleTestService::class], $moduleAttribute->injectables);
        $this->assertEquals([ModuleTestService::class], $moduleAttribute->exports);
    }
}
