<?php

declare(strict_types=1);

namespace Hyperdrive\Support;

class PathBuilder
{
    public static function build(string $prefix, string $path): string
    {
        $prefix = trim($prefix, '/');
        $path = trim($path, '/');

        if ($prefix === '' && $path === '') {
            return '/';
        }

        if ($prefix && $path) {
            return '/' . $prefix . '/' . $path;
        }

        $result = $prefix . $path;

        // Ensure the result always starts with a slash
        if ($result !== '' && $result[0] !== '/') {
            $result = '/' . $result;
        }

        return $result;
    }
}
