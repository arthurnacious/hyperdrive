<?php

declare(strict_types=1);

namespace Hyperdrive\Application;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final class Module
{
    public function __construct(
        public array $imports = [],
        public array $controllers = [],
        public array $injectables = [],
        public array $exports = []
    ) {}
}
