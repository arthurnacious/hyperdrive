<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Configuration;

use Hyperdrive\Config\Config;
use Hyperdrive\Config\ConfigLoader;
use Hyperdrive\Config\Environment;
use PHPUnit\Framework\TestCase;

class EnvironmentConfigTest extends TestCase
{
    protected function setUp(): void
    {
        Config::clear();
        Environment::clear();
    }

    public function test_it_loads_environment_specific_config(): void
    {
        $_ENV['APP_ENV'] = 'testing';

        $configDir = __DIR__ . '/fixtures/config';
        $envConfigDir = __DIR__ . '/fixtures/config/testing';

        ConfigLoader::loadFromDirectory($configDir);
        ConfigLoader::loadFromDirectory($envConfigDir);

        $this->assertEquals('Hyperdrive Test', Config::get('app.name'));
        $this->assertTrue(Config::get('app.debug'));
    }

    public function test_it_overrides_base_config_with_environment_config(): void
    {
        $_ENV['APP_ENV'] = 'production';

        $configDir = __DIR__ . '/fixtures/config';
        $envConfigDir = __DIR__ . '/fixtures/config/production';

        ConfigLoader::loadFromDirectory($configDir);
        ConfigLoader::loadFromDirectory($envConfigDir);

        // Overridden value
        $this->assertEquals('Hyperdrive Production', Config::get('app.name'));
        // Original value preserved
        $this->assertEquals(3000, Config::get('app.port'));
    }
}
