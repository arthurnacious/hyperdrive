<?php

declare(strict_types=1);

namespace Hyperdrive\Http\Dto\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class ValidateWith implements ValidatorInterface
{
    public function __construct(
        private string $method
    ) {}

    public function validate(mixed $value, string $propertyName): bool
    {
        // This validator doesn't validate directly
        // It's a marker for Dto::runFieldCustomValidations()
        // The $method property is used by Dto, not here
        return true;
    }

    public function getMessage(): string
    {
        return '';
    }

    public function getMethod(): string
    {
        return $this->method;
    }

    public function getMethod(): string
    {
        return $this->method;
    }
}
