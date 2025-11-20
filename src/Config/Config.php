<?php

declare(strict_types=1);

namespace Hyperdrive\Config;

class Config
{
    private array $config = [];
    private static ?self $instance = null;
    private bool $loaded = false;

    private function __construct()
    {
        // Private constructor for singleton
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
            self::$instance->loadConfig();
        }

        return self::$instance;
    }

    private function loadConfig(): void
    {
        if ($this->loaded) {
            return;
        }

        // 1. Load framework defaults first
        $frameworkConfigPath = __DIR__ . '/../../config';
        if (is_dir($frameworkConfigPath)) {
            $this->loadConfigFromDirectory($frameworkConfigPath);
        }

        // 2. Load project overrides (if they exist)
        $projectConfigPath = getcwd() . '/config';
        if (is_dir($projectConfigPath)) {
            $this->loadConfigFromDirectory($projectConfigPath);
        }

        $this->loaded = true;
    }

    private function loadConfigFromDirectory(string $configDir): void
    {
        $files = glob($configDir . '/*.php');

        foreach ($files as $file) {
            $filename = pathinfo($file, PATHINFO_FILENAME);
            $config = require $file;

            if (is_array($config)) {
                // Merge with existing config (project overrides framework)
                $existing = $this->getValue($filename, []);
                $merged = $this->mergeConfig($existing, $config);
                $this->setValue($filename, $merged);
            }
        }
    }

    private function mergeConfig(array $existing, array $new): array
    {
        foreach ($new as $key => $value) {
            if (is_array($value) && isset($existing[$key]) && is_array($existing[$key])) {
                $existing[$key] = $this->mergeConfig($existing[$key], $value);
            } else {
                $existing[$key] = $value;
            }
        }

        return $existing;
    }

    public static function clear(): void
    {
        self::$instance = null;
    }

    public static function set(string $key, mixed $value): void
    {
        $instance = self::getInstance();
        $instance->setValue($key, $value);
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $instance = self::getInstance();
        return $instance->getValue($key, $default);
    }

    public static function has(string $key): bool
    {
        $instance = self::getInstance();
        return $instance->hasValue($key);
    }

    private function setValue(string $key, mixed $value): void
    {
        $keys = explode('.', $key);
        $current = &$this->config;

        foreach ($keys as $k) {
            if (!isset($current[$k]) || !is_array($current[$k])) {
                $current[$k] = [];
            }
            $current = &$current[$k];
        }

        $current = $value;
    }

    private function getValue(string $key, mixed $default = null): mixed
    {
        $keys = explode('.', $key);
        $current = $this->config;

        foreach ($keys as $k) {
            if (!isset($current[$k])) {
                return $default;
            }
            $current = $current[$k];
        }

        return $current;
    }

    private function hasValue(string $key): bool
    {
        $keys = explode('.', $key);
        $current = $this->config;

        foreach ($keys as $k) {
            if (!isset($current[$k])) {
                return false;
            }
            $current = $current[$k];
        }

        return true;
    }
}
