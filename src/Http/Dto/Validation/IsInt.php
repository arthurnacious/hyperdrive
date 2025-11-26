<?php

declare(strict_types=1);

namespace Hyperdrive\Http\Dto\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsInt implements ValidatorInterface
{
    public function validate(mixed $value, string $field): bool
    {
        return is_int($value);
    }

    public function getMessage(): string
    {
        return 'Must be an integer';
    }
}
