<?php

declare(strict_types=1);

namespace Hyperdrive\Attributes\Http\Verbs;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class Get
{
    public function __construct(public string $path = '') {}
}
