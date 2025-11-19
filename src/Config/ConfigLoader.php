<?php

declare(strict_types=1);

namespace Hyperdrive\Config;

class ConfigLoader
{
    public static function loadFromDirectory(string $configDir): void
    {
        if (!is_dir($configDir)) {
            throw new \InvalidArgumentException("Config directory does not exist: {$configDir}");
        }

        $files = glob($configDir . '/*.php');

        foreach ($files as $file) {
            self::loadFile($file);
        }
    }

    public static function loadFile(string $filePath): void
    {
        if (!file_exists($filePath)) {
            throw new \InvalidArgumentException("Config file does not exist: {$filePath}");
        }

        $config = require $filePath;

        if (!is_array($config)) {
            throw new \RuntimeException("Config file must return an array: {$filePath}");
        }

        $filename = pathinfo($filePath, PATHINFO_FILENAME);

        // Merge with existing config instead of replacing
        $existing = Config::get($filename, []);
        $merged = self::mergeConfig($existing, $config);
        Config::set($filename, $merged);
    }

    public static function loadFromArray(array $config): void
    {
        foreach ($config as $key => $value) {
            // Merge arrays, replace scalars
            $existing = Config::get($key);
            if (is_array($existing) && is_array($value)) {
                $value = self::mergeConfig($existing, $value);
            }
            Config::set($key, $value);
        }
    }

    private static function mergeConfig(array $existing, array $new): array
    {
        foreach ($new as $key => $value) {
            if (is_array($value) && isset($existing[$key]) && is_array($existing[$key])) {
                $existing[$key] = self::mergeConfig($existing[$key], $value);
            } else {
                $existing[$key] = $value;
            }
        }

        return $existing;
    }
}
