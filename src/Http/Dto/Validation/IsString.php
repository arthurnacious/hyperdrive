<?php

declare(strict_types=1);

namespace Hyperdrive\Http\Dto\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsString implements ValidatorInterface
{
    public function validate(mixed $value, string $field): bool
    {
        return is_string($value);
    }

    public function getMessage(): string
    {
        return 'Must be a string';
    }
}
