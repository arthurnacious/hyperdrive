<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\ModuleSystem;

use Hyperdrive\Application\Module;
use Hyperdrive\Application\ModuleRegistry;
use PHPUnit\Framework\TestCase;

class EdgeCasesPlainModule {}
class EdgeCasesEmptyModule {}

class ModuleEdgeCasesTest extends TestCase
{
    public function test_it_handles_module_without_attributes(): void
    {
        $registry = new ModuleRegistry();

        $registry->register(EdgeCasesPlainModule::class);

        $this->assertTrue($registry->has(EdgeCasesPlainModule::class));
        $this->assertEquals([], $registry->getControllers(EdgeCasesPlainModule::class));
        $this->assertEquals([], $registry->getInjectables(EdgeCasesPlainModule::class));
    }

    public function test_it_handles_empty_module(): void
    {
        $registry = new ModuleRegistry();

        $registry->register(EdgeCasesEmptyModule::class);

        $this->assertTrue($registry->has(EdgeCasesEmptyModule::class));
        $this->assertEquals([], $registry->getControllers(EdgeCasesEmptyModule::class));
        $this->assertEquals([], $registry->getInjectables(EdgeCasesEmptyModule::class));
    }
}
