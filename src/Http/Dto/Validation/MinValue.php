<?php

declare(strict_types=1);

namespace Hyperdrive\Http\Dto\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class MinValue implements ValidatorInterface
{
    public function __construct(private int $min) {}

    public function validate(mixed $value, string $field): bool
    {
        return is_int($value) && $value >= $this->min;
    }

    public function getMessage(): string
    {
        return "Must be at least {$this->min}";
    }
}
