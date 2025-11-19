<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Configuration;

use Hyperdrive\Config\Config;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    protected function setUp(): void
    {
        // Clear any existing config instance
        Config::clear();
    }

    public function test_it_can_get_values_using_dot_notation(): void
    {
        Config::set('app.name', 'Hyperdrive');
        Config::set('app.debug', true);

        $this->assertEquals('Hyperdrive', Config::get('app.name'));
        $this->assertTrue(Config::get('app.debug'));
    }

    public function test_it_returns_default_for_missing_keys(): void
    {
        $value = Config::get('non.existent.key', 'default');

        $this->assertEquals('default', $value);
    }

    public function test_it_can_check_if_key_exists(): void
    {
        Config::set('app.name', 'Hyperdrive');

        $this->assertTrue(Config::has('app.name'));
        $this->assertFalse(Config::has('non.existent.key'));
    }
}
