<?php

declare(strict_types=1);

namespace Hyperdrive\Http\Dto\Validation;

interface CrossFieldValidatorInterface extends ValidatorInterface
{
    public function validateWithContext(mixed $value, array $allValues, string $field): bool;
}
