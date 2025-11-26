<?php

declare(strict_types=1);

namespace Hyperdrive\Http\Dto\Validation;

use Attribute;

interface ValidatorInterface
{
    public function validate(mixed $value, string $field): bool;
    public function getMessage(): string;
}
