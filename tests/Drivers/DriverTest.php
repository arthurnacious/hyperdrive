<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Drivers;

use Hyperdrive\Application\Hyperdrive;
use Hyperdrive\Config\Environment;
use Hyperdrive\Contracts\DriverInterface;
use PHPUnit\Framework\TestCase;

class TestModule {}

class DriverTest extends TestCase
{
    protected function setUp(): void
    {
        Environment::clear();
    }

    public function test_it_can_create_hyperdrive_with_auto_detection(): void
    {
        $app = Hyperdrive::create(
            rootModule: TestModule::class,
            driver: 'auto',
            environment: 'testing'
        );

        $this->assertInstanceOf(Hyperdrive::class, $app);
    }

    public function test_it_throws_exception_for_unsupported_driver(): void
    {
        $this->expectException(\Hyperdrive\Exceptions\DriverNotFoundException::class);

        Hyperdrive::create(
            rootModule: TestModule::class,
            driver: 'unsupported',
            environment: 'testing'
        );
    }
}
