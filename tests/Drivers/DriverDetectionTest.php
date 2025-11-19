<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Drivers;

use Hyperdrive\Application\Hyperdrive;
use Hyperdrive\Drivers\RoadstarDriver;
use PHPUnit\Framework\TestCase;

class DriverDetectionTest extends TestCase
{
    public function test_it_can_explicitly_select_roadstar_driver(): void
    {
        $app = Hyperdrive::create(
            rootModule: TestModule::class,
            driver: 'roadstar',
            environment: 'testing'
        );

        $this->assertInstanceOf(RoadstarDriver::class, $app->getDriver());
    }

    public function test_driver_can_be_booted_and_stopped(): void
    {
        $app = Hyperdrive::create(
            rootModule: TestModule::class,
            driver: 'roadstar',
            environment: 'testing'
        );

        $driver = $app->getDriver();

        $app->boot();
        $this->assertTrue($driver->isRunning());

        $driver->stop();
        $this->assertFalse($driver->isRunning());
    }

    public function test_it_throws_exception_for_missing_extension_when_explicitly_requested(): void
    {
        if (extension_loaded('openswoole')) {
            $this->markTestSkipped('OpenSwoole extension is loaded, cannot test missing extension');
        }

        $this->expectException(\Hyperdrive\Exceptions\DriverNotFoundException::class);
        $this->expectExceptionMessage('OpenSwoole extension is not installed');

        Hyperdrive::create(
            rootModule: TestModule::class,
            driver: 'openswoole',
            environment: 'testing'
        );
    }

    public function test_roadstar_is_available_when_no_other_extensions_are_present(): void
    {
        $app = Hyperdrive::create(
            rootModule: TestModule::class,
            driver: 'roadstar',
            environment: 'testing'
        );

        $this->assertInstanceOf(RoadstarDriver::class, $app->getDriver());

        // The driver should NOT be running until we boot it
        $this->assertFalse($app->getDriver()->isRunning());

        // After booting, it should be running
        $app->boot();
        $this->assertTrue($app->getDriver()->isRunning());
    }
}
