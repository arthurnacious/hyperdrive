<?php

declare(strict_types=1);

namespace Hyperdrive\Http;

use Hyperdrive\Http\Dto\Validation\CrossFieldValidatorInterface;
use Hyperdrive\Http\Dto\Validation\ValidationException;
use Hyperdrive\Http\Dto\Validation\ValidatorInterface;
use Hyperdrive\Http\Dto\Validation\ValidateWith;

abstract class Dto
{
    private static array $validationCache = [];
    protected array $errors = [];
    protected array $context = [];

    /**
     * Constructor that DtoFactory will use
     * @param array $data The data to hydrate
     * @param array $context Optional context for custom validation
     */
    public function __construct(array $data, array $context = [])
    {
        $this->context = $context;
        $this->errors = [];

        // 1. Always validate types first
        $this->validateTypes($data);

        // 2. If type validation fails, throw immediately
        if (!empty($this->errors)) {
            throw new ValidationException($this->errors);
        }

        // 3. Convert and assign values
        $this->hydrate($data);

        // 4. Run attribute-based validation (includes cross-field)
        $this->validateAttributes();

        // 5. If still errors, throw
        if (!empty($this->errors)) {
            throw new ValidationException($this->errors);
        }

        // 6. CUSTOM VALIDATION WITH SERVICES WILL BE CALLED BY DTOFACTORY
        //    via reflection after constructor
    }

    /**
     * Validate data types against property declarations
     */
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
            'int' => is_numeric($value) || ($value === null && $type->allowsNull()),
            'float' => is_numeric($value) || ($value === null && $type->allowsNull()),
            'bool' => is_bool($value) || ($value === null && $type->allowsNull()),
            'string' => is_string($value) || ($value === null && $type->allowsNull()),
            'array' => is_array($value) || ($value === null && $type->allowsNull()),
            default => true
        };

        if (!$isValid) {
            $this->errors[$field][] = "Must be of type {$typeName}";
        }
    }

    /**
     * Assign values to properties with type conversion
     */
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

    /**
     * Run attribute-based validation (#[IsEmail], #[Equals], etc.)
     * Includes cross-field validation
     */
    private function validateAttributes(): void
    {
        $className = static::class;

        if (!isset(self::$validationCache[$className])) {
            self::$validationCache[$className] = $this->buildValidationRules();
        }

        $rules = self::$validationCache[$className];
        $allValues = $this->collectAllValues();

        foreach ($rules as $propertyName => $validators) {
            $value = $this->{$propertyName} ?? null;

            foreach ($validators as $validator) {
                if ($validator instanceof CrossFieldValidatorInterface) {
                    // Cross-field validators get all values
                    if (!$validator->validateWithContext($value, $allValues, $propertyName)) {
                        $this->addError($propertyName, $validator->getMessage());
                    }
                } elseif ($validator instanceof ValidateWith) {
                    // ValidateWith attributes handled by DtoFactory
                    // They will call custom validation methods with services
                    continue;
                } else {
                    // Standard validators
                    if (!$validator->validate($value, $propertyName)) {
                        $this->addError($propertyName, $validator->getMessage());
                    }
                }
            }
        }
    }

    /**
     * Collect all property values for cross-field validation
     */
    private function collectAllValues(): array
    {
        $values = [];
        $reflection = new \ReflectionClass($this);

        foreach ($reflection->getProperties() as $property) {
            $values[$property->getName()] = $property->getValue($this);
        }

        return $values;
    }

    /**
     * Build cache of validation rules from attributes
     */
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

    /**
     * Add error to specific field
     */
    public function addError(string $field, string|array $messages): void
    {
        if (!isset($this->errors[$field])) {
            $this->errors[$field] = [];
        }

        if (is_array($messages)) {
            foreach ($messages as $message) {
                $this->errors[$field][] = $message;
            }
        } else {
            $this->errors[$field][] = $messages;
        }
    }

    /**
     * Get context value (for DtoFactory to pass services)
     */
    protected function getContext(string $key, mixed $default = null): mixed
    {
        return $this->context[$key] ?? $default;
    }

    /**
     * Add error if condition is true
     */
    public function addErrorIf(string $field, bool $condition, string $message): void
    {
        if ($condition) {
            $this->addError($field, $message);
        }
    }

    /**
     * Add error unless condition is true (add if false)
     */
    public function addErrorUnless(string $field, bool $condition, string $message): void
    {
        if (!$condition) {
            $this->addError($field, $message);
        }
    }

    /**
     * Check if DTO is valid
     */
    public function isValid(): bool
    {
        return empty($this->errors);
    }

    /**
     * Get all validation errors
     */
    public function getErrors(): array
    {
        return $this->errors;
    }

    /**
     * Convert DTO to array (for responses)
     */
    public function toArray(): array
    {
        $result = [];
        $reflection = new \ReflectionClass($this);

        foreach ($reflection->getProperties() as $property) {
            if ($property->isPublic()) {
                $propertyName = $property->getName();
                $result[$propertyName] = $this->{$propertyName};
            }
        }

        return $result;
    }

    /**
     * Check if specific field has errors
     */
    public function hasErrors(?string $field = null): bool
    {
        if ($field === null) {
            return !empty($this->errors);
        }

        return isset($this->errors[$field]);
    }
}
