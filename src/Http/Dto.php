<?php

declare(strict_types=1);

namespace Hyperdrive\Http;

use Hyperdrive\Http\Dto\Validation\ValidationException;
use Hyperdrive\Http\Dto\Validation\ValidatorInterface;

abstract class Dto
{
    private static array $validationCache = [];
    private array $errors = [];

    public function __construct(array $data)
    {
        $this->errors = [];

        // 1. Validate types first
        $this->validateTypes($data);

        // 2. If type validation fails, throw immediately
        if (!empty($this->errors)) {
            throw new ValidationException($this->errors);
        }

        // 3. Convert and assign values
        $this->hydrate($data);

        // 4. Validate business rules
        $this->validate();
    }

    private function validateTypes(array $data): void
    {
        $reflection = new \ReflectionClass($this);

        foreach ($data as $key => $value) {
            if ($reflection->hasProperty($key)) {
                $property = $reflection->getProperty($key);
                $type = $property->getType();

                if ($type && $type->isBuiltin()) {
                    $this->validateType($key, $value, $type);
                }
            }
        }
    }

    private function validateType(string $field, mixed $value, \ReflectionType $type): void
    {
        if (!$type instanceof \ReflectionNamedType) {
            return;
        }

        $typeName = $type->getName();
        $isValid = match ($typeName) {
            'int' => is_numeric($value) || $value === null,
            'float' => is_numeric($value) || $value === null,
            'bool' => is_bool($value) || $value === null,
            'string' => is_string($value) || $value === null,
            'array' => is_array($value) || $value === null,
            default => true
        };

        if (!$isValid) {
            $this->errors[$field][] = "Must be of type {$typeName}";
        }
    }

    private function hydrate(array $data): void
    {
        $reflection = new \ReflectionClass($this);

        foreach ($data as $key => $value) {
            if ($reflection->hasProperty($key)) {
                $property = $reflection->getProperty($key);
                $type = $property->getType();

                if ($type && !$type->isBuiltin()) {
                    // Skip non-builtin types for now (objects, etc.)
                    continue;
                }

                // Convert value to expected type before assignment
                $convertedValue = $this->convertToType($value, $type);
                $this->{$key} = $convertedValue;
            }
        }
    }

    private function convertToType(mixed $value, ?\ReflectionType $type): mixed
    {
        if ($type === null) {
            return $value;
        }

        if (!$type instanceof \ReflectionNamedType) {
            return $value;
        }

        $typeName = $type->getName();

        // Handle null values for nullable types
        if ($value === null && $type->allowsNull()) {
            return null;
        }

        // Convert to expected type
        return match ($typeName) {
            'int' => (int) $value,
            'float' => (float) $value,
            'bool' => (bool) $value,
            'string' => (string) $value,
            'array' => is_array($value) ? $value : [$value],
            default => $value
        };
    }

    private function validate(): void
    {
        $className = static::class;

        // 🚀 Cache validation rules for performance
        if (!isset(self::$validationCache[$className])) {
            self::$validationCache[$className] = $this->buildValidationRules();
        }

        $rules = self::$validationCache[$className];
        $this->errors = [];

        // 🚀 Fast validation with cached rules
        foreach ($rules as $propertyName => $validators) {
            $value = $this->{$propertyName} ?? null;
            foreach ($validators as $validator) {
                if (!$validator->validate($value, $propertyName)) {
                    $this->errors[$propertyName][] = $validator->getMessage();
                }
            }
        }

        if (!empty($this->errors)) {
            throw new ValidationException($this->errors);
        }
    }

    private function buildValidationRules(): array
    {
        $rules = [];
        $reflection = new \ReflectionClass($this);

        foreach ($reflection->getProperties() as $property) {
            $propertyName = $property->getName();
            $rules[$propertyName] = [];

            foreach ($property->getAttributes() as $attribute) {
                $validator = $attribute->newInstance();
                if ($validator instanceof ValidatorInterface) {
                    $rules[$propertyName][] = $validator;
                }
            }
        }

        return $rules;
    }

    public function isValid(): bool
    {
        return empty($this->errors);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
