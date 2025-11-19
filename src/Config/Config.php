<?php

declare(strict_types=1);

namespace Hyperdrive\Config;

class Config
{
    private static ?self $instance = null;
    private array $config = [];

    private function __construct()
    {
        // Private constructor for singleton
    }

    public static function getInstance(): self
    {
        if (self::$instance === null) {
            self::$instance = new self();
        }

        return self::$instance;
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
