<?php

declare(strict_types=1);

namespace Hyperdrive\Attributes\WebSocket;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class WebSocketGateway
{
    public function __construct(
        public string $path = '',
        public string $prefix = 'ws'
    ) {}
}
