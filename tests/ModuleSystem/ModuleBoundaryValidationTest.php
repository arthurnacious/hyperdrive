<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\ModuleSystem\Boundaries;

use Hyperdrive\Application\Module;
use Hyperdrive\Application\ModuleRegistry;
use Hyperdrive\Exceptions\ModuleBoundaryException;
use PHPUnit\Framework\TestCase;

// --- fixtures shared across scenarios ---

class SharedService
{
}

interface SharedInterface
{
}

class SharedImplementation implements SharedInterface
{
}

class UnrelatedClass
{
}

class DependsOnSharedService
{
    public function __construct(public SharedService $service)
    {
    }
}

class DependsOnSharedInterface
{
    public function __construct(public SharedInterface $service)
    {
    }
}

class DependsOnUnregisteredClass
{
    public function __construct(public UnrelatedClass $thing)
    {
    }
}

// Owns SharedService but does NOT export it.
#[Module(injectables: [SharedService::class])]
class ProviderModuleWithoutExport
{
}

// Owns SharedService and DOES export it.
#[Module(injectables: [SharedService::class], exports: [SharedService::class])]
class ProviderModuleWithExport
{
}

// Owns SharedInterface => SharedImplementation and exports the interface.
#[Module(
    injectables: [SharedInterface::class => SharedImplementation::class],
    exports: [SharedInterface::class],
)]
class ProviderModuleExportingInterface
{
}

// Imports the exporting module and consumes its export - should be fine.
#[Module(
    imports: [ProviderModuleWithExport::class],
    controllers: [DependsOnSharedService::class],
)]
class ConsumerModuleImportingAndExported
{
}

// Imports the interface-exporting module and depends on the interface.
#[Module(
    imports: [ProviderModuleExportingInterface::class],
    controllers: [DependsOnSharedInterface::class],
)]
class ConsumerModuleUsingInterface
{
}

// Imports a module that owns SharedService but never exports it.
#[Module(
    imports: [ProviderModuleWithoutExport::class],
    controllers: [DependsOnSharedService::class],
)]
class ConsumerModuleMissingExport
{
}

// Depends on SharedService from ProviderModuleWithExport WITHOUT importing it.
#[Module(controllers: [DependsOnSharedService::class])]
class ConsumerModuleMissingImport
{
}

// Root importing both the exporting provider and the non-importing consumer
// as siblings, so the dependency is genuinely owned but unreachable.
#[Module(imports: [ProviderModuleWithExport::class, ConsumerModuleMissingImport::class])]
class SiblingRootModule
{
}

// A module providing and consuming its own service - same-module
// dependencies are always allowed regardless of exports.
#[Module(controllers: [DependsOnUnregisteredClass::class])]
class ModuleDependingOnUnregisteredClass
{
}

class ModuleBoundaryValidationTest extends TestCase
{
    public function test_it_allows_a_dependency_on_an_exported_and_imported_service(): void
    {
        $registry = new ModuleRegistry();
        $registry->register(ConsumerModuleImportingAndExported::class);

        $registry->validateModuleBoundaries();

        $this->addToAssertionCount(1); // no exception thrown
    }

    public function test_it_allows_a_dependency_on_an_exported_and_imported_interface(): void
    {
        $registry = new ModuleRegistry();
        $registry->register(ConsumerModuleUsingInterface::class);

        $registry->validateModuleBoundaries();

        $this->addToAssertionCount(1);
    }

    public function test_it_ignores_dependencies_on_classes_no_module_registered(): void
    {
        $registry = new ModuleRegistry();
        $registry->register(ModuleDependingOnUnregisteredClass::class);

        $registry->validateModuleBoundaries();

        $this->addToAssertionCount(1);
    }

    public function test_it_throws_when_the_owning_module_does_not_export_the_dependency(): void
    {
        $registry = new ModuleRegistry();
        $registry->register(ConsumerModuleMissingExport::class);

        $this->expectException(ModuleBoundaryException::class);
        $this->expectExceptionMessageMatches('/not in its exports/');

        $registry->validateModuleBoundaries();
    }

    public function test_it_throws_when_the_consuming_module_does_not_import_the_owning_module(): void
    {
        $registry = new ModuleRegistry();
        $registry->register(SiblingRootModule::class);

        $this->expectException(ModuleBoundaryException::class);
        $this->expectExceptionMessageMatches('/does not import/');

        $registry->validateModuleBoundaries();
    }
}
