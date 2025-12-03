<?php

declare(strict_types=1);

namespace Hyperdrive\Attributes\Http\Verbs;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD)]
final class Options
{
    public function __construct(public string $path = '') {}
}
