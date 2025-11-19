<?php

declare(strict_types=1);

namespace Hyperdrive\Attributes\Http;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Route
{
    public function __construct(public string $prefix = '') {}
}
