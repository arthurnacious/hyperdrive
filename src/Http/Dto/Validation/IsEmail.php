<?php

declare(strict_types=1);

namespace Hyperdrive\Http\Dto\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsEmail implements ValidatorInterface
{
    public function validate(mixed $value, string $field): bool
    {
        return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;
    }

    public function getMessage(): string
    {
        return 'Must be a valid email address';
    }
}
