<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Configuration;

use Hyperdrive\Config\Config;
use Hyperdrive\Config\ConfigLoader;
use PHPUnit\Framework\TestCase;

class ConfigFileLoadingTest extends TestCase
{
    protected function setUp(): void
    {
        Config::clear();
    }

    public function test_it_can_load_config_from_php_files(): void
    {
        $configDir = __DIR__ . '/fixtures/config';

        ConfigLoader::loadFromDirectory($configDir);

        $this->assertEquals('Hyperdrive App', Config::get('app.name'));
        $this->assertEquals(3000, Config::get('app.port'));
        $this->assertEquals(['web', 'api'], Config::get('app.middleware'));
    }

    public function test_it_merges_config_from_multiple_files(): void
    {
        $configDir = __DIR__ . '/fixtures/config';

        ConfigLoader::loadFromDirectory($configDir);

        $this->assertEquals('Hyperdrive App', Config::get('app.name'));
    }
}
