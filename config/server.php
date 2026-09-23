<?php

declare(strict_types=1);

return [
    'http' => [
        'host' => $_ENV['HYPERDRIVE_HOST'] ?? '0.0.0.0',
        'port' => (int) ($_ENV['HYPERDRIVE_PORT'] ?? 300),
        // Recycle each worker after this many requests to bound the impact
        // of any slow memory growth in application code. 0 = unlimited.
        'max_request' => (int) ($_ENV['HYPERDRIVE_MAX_REQUEST'] ?? 10000),
    ],
    'websocket' => [
        'enabled' => $_ENV['HYPERDRIVE_WEBSOCKET_ENABLED'] ?? true,
        'host' => $_ENV['HYPERDRIVE_WEBSOCKET_HOST'] ?? '0.0.0.0',
        'port' => (int) ($_ENV['HYPERDRIVE_WEBSOCKET_PORT'] ?? 3000), // Same as HTTP by default
    ],
    // Off by default: lets a project serve a public/ directory (uploads,
    // a storage:link-style symlink, etc.) straight from the server driver
    // instead of writing its own passthrough controller/route for it.
    'static' => [
        'enabled' => filter_var($_ENV['HYPERDRIVE_STATIC_ENABLED'] ?? false, FILTER_VALIDATE_BOOLEAN),
        'document_root' => $_ENV['HYPERDRIVE_STATIC_DOCUMENT_ROOT'] ?? null,
    ],
];
