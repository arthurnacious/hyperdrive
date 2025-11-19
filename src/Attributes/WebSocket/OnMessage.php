<?php

declare(strict_types=1);

namespace Hyperdrive\Attributes\WebSocket;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class OnMessage
{
    public function __construct(public ?string $type = null) {}
}
