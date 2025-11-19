<?php

declare(strict_types=1);

namespace Hyperdrive\Config;

class Environment
{
    private static ?string $environment = null;

    public static function get(): string
    {
        if (self::$environment === null) {
            self::$environment = self::detectEnvironment();
        }

        return self::$environment;
    }

    public static function clear(): void
    {
        self::$environment = null;
    }

    public static function is(string $environment): bool
    {
        return self::get() === $environment;
    }

    public static function isProduction(): bool
    {
        return self::is('production');
    }

    public static function isDevelopment(): bool
    {
        return self::is('development');
    }

    public static function isTesting(): bool
    {
        return self::is('testing');
    }

    public static function isLocal(): bool
    {
        return in_array(self::get(), ['local', 'development', 'testing']);
    }

    private static function detectEnvironment(): string
    {
        $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'production';

        // Validate environment
        $validEnvironments = ['production', 'development', 'testing', 'staging', 'local'];

        if (!in_array($env, $validEnvironments)) {
            throw new \InvalidArgumentException("Invalid environment: {$env}. Must be one of: " . implode(', ', $validEnvironments));
        }

        return $env;
    }
}
