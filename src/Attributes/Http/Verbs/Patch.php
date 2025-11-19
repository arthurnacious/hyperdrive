<?php

declare(strict_types=1);

namespace Hyperdrive\Attributes\Http\Verbs;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class Patch
{
    public function __construct(public string $path = '') {}
}
