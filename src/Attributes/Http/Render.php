<?php

declare(strict_types=1);

namespace Hyperdrive\Attributes\Http;

use Attribute;

/**
 * Render the route's array/object return value through the bound
 * ViewEngineInterface instead of serializing it to JSON - the
 * equivalent of NestJS's @Render('view').
 */
#[Attribute(Attribute::TARGET_METHOD)]
final class Render
{
    public function __construct(public string $view) {}
}
