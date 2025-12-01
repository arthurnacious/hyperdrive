<?php

declare(strict_types=1);

namespace Hyperdrive\Http\Dto\Validation;

interface ValidatorInterface
{
    public function validate(mixed $value, string $field): bool;
    public function getMessage(): string;
}
