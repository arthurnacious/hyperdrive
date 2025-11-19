<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Configuration;

use Hyperdrive\Config\Environment;
use PHPUnit\Framework\TestCase;

class EnvironmentTest extends TestCase
{
    protected function setUp(): void
    {
        // Clear environment cache
        Environment::clear();
    }

    public function test_it_detects_environment_from_env_variable(): void
    {
        $_ENV['APP_ENV'] = 'testing';

        $this->assertEquals('testing', Environment::get());
    }

    public function test_it_defaults_to_production(): void
    {
        unset($_ENV['APP_ENV']);

        $this->assertEquals('production', Environment::get());
    }

    public function test_it_can_check_if_environment_is_specific_value(): void
    {
        $_ENV['APP_ENV'] = 'development';

        $this->assertTrue(Environment::is('development'));
        $this->assertFalse(Environment::is('production'));
    }

    public function test_it_can_check_if_environment_is_local(): void
    {
        $_ENV['APP_ENV'] = 'local';

        $this->assertTrue(Environment::isLocal());
        $this->assertFalse(Environment::isProduction());
    }

    public function test_it_can_check_if_environment_is_production(): void
    {
        $_ENV['APP_ENV'] = 'production';

        $this->assertTrue(Environment::isProduction());
        $this->assertFalse(Environment::isLocal());
    }
}
