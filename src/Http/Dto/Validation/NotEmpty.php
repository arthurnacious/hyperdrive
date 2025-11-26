<?php

declare(strict_types=1);

namespace Hyperdrive\Http\Dto\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class NotEmpty implements ValidatorInterface
{
    public function validate(mixed $value, string $field): bool
    {
        return !empty($value);
    }

    public function getMessage(): string
    {
        return 'Cannot be empty';
    }
}
