<?php

declare(strict_types=1);

namespace Hyperdrive\Http\Dto\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
class IsArray implements ValidatorInterface
{
    public function __construct(
        private ?string $type = null,
        private ?array $valueIn = null
    ) {}

    public function validate(mixed $value, string $field): bool
    {
        if (!is_array($value)) {
            return false;
        }

        if ($this->type !== null) {
            foreach ($value as $item) {
                if (gettype($item) !== $this->type) {
                    return false;
                }
            }
        }

        if ($this->valueIn !== null) {
            foreach ($value as $item) {
                if (!in_array($item, $this->valueIn, true)) {
                    return false;
                }
            }
        }

        return true;
    }

    public function getMessage(): string
    {
        $message = 'Must be an array';
        if ($this->type) {
            $message .= " of {$this->type}s";
        }
        if ($this->valueIn) {
            $message .= " with values in: " . implode(', ', $this->valueIn);
        }
        return $message;
    }
}
