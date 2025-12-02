<?php

declare(strict_types=1);

namespace Hyperdrive\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS | Attribute::TARGET_METHOD)]
class Middleware
{
    /**
     * @param class-string<\Hyperdrive\Http\Middleware\MiddlewareInterface>[] $middlewares
     */
    public function __construct(
        public array $middlewares = []
    ) {}
}
