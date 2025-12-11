<?php

declare(strict_types=1);

namespace Hyperdrive\Http;

use Hyperdrive\Http\Dto\Validation\ValidationException;
use Hyperdrive\Http\Dto\Validation\ValidatorInterface;

abstract class Dto
{
    private static array $validationCache = [];
    protected array $errors = [];
    protected array $context = [];

    public function __construct(array $data, array $context = [])
    {
        $this->context = $context;
        $this->errors = [];

        $this->validateTypes($data);

        if (!empty($this->errors)) {
            throw new ValidationException($this->errors);
        }

        $this->hydrate($data);
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
                    continue;
                }

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

        if ($value === null && $type->allowsNull()) {
            return null;
        }

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

        if (!isset(self::$validationCache[$className])) {
            self::$validationCache[$className] = $this->buildValidationRules();
        }

        $rules = self::$validationCache[$className];
        $this->errors = [];

        foreach ($rules as $propertyName => $validators) {
            $value = $this->{$propertyName} ?? null;
            foreach ($validators as $validator) {
                if (!$validator->validate($value, $propertyName)) {
                    $this->errors[$propertyName][] = $validator->getMessage();
                }
            }
        }

        $this->runFieldCustomValidations();
        $this->runPostValidation();

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

    private function runFieldCustomValidations(): void
    {
        $reflection = new \ReflectionClass($this);

        foreach ($reflection->getProperties() as $property) {
            foreach ($property->getAttributes() as $attribute) {
                $instance = $attribute->newInstance();

                if ($instance instanceof \Hyperdrive\Http\Dto\Validation\ValidateWith) {
                    // 🔥 Use getter instead of direct property access
                    $methodName = $instance->getMethod();
                    $propertyName = $property->getName();
                    $propertyValue = $this->{$propertyName} ?? null;

                    if (method_exists($this, $methodName)) {
                        $method = new \ReflectionMethod($this, $methodName);
                        $params = [];

                        foreach ($method->getParameters() as $param) {
                            $paramName = $param->getName();

                            if ($paramName === 'field' || $paramName === 'property') {
                                $params[] = $propertyName;
                            } else {
                                $params[] = $propertyValue;
                            }
                        }

                        $method->invokeArgs($this, $params);
                    }
                }
            }
        }
    }

    private function runPostValidation(): void
    {
        $reflection = new \ReflectionClass($this);

        // Check for validateAll() method
        if ($reflection->hasMethod('validateAll')) {
            $method = $reflection->getMethod('validateAll');
            if ($method->isPrivate() && !$method->isAbstract()) {
                $method->invoke($this);
            }
        }

        foreach ($reflection->getMethods() as $method) {
            $methodName = $method->getName();
            if (
                (str_starts_with($methodName, 'validatePost') ||
                    str_starts_with($methodName, 'validateAfter')) &&
                $method->isPrivate() &&
                !$method->isAbstract()
            ) {
                $method->invoke($this);
            }
        }
    }

    public function addError(string $field, string|array ...$messages): void
    {
        foreach ($messages as $message) {
            if (is_array($message)) {
                foreach ($message as $msg) {
                    $this->errors[$field][] = $msg;
                }
            } else {
                $this->errors[$field][] = $message;
            }
        }
    }

    protected function getContext(string $key, mixed $default = null): mixed
    {
        return $this->context[$key] ?? $default;
    }

    public function addErrorIf(string $field, bool $condition, string $message): void
    {
        if ($condition) {
            $this->addError($field, $message);
        }
    }

    public function addErrorUnless(string $field, bool $condition, string $message): void
    {
        if (!$condition) {
            $this->addError($field, $message);
        }
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
