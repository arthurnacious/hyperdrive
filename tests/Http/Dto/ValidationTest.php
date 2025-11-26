<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Http\Dto;

use Hyperdrive\Http\Dto;
use Hyperdrive\Http\Dto\Validation\IsArray;
use Hyperdrive\Http\Dto\Validation\IsEmail;
use Hyperdrive\Http\Dto\Validation\IsInt;
use Hyperdrive\Http\Dto\Validation\IsString;
use Hyperdrive\Http\Dto\Validation\MinLength;
use Hyperdrive\Http\Dto\Validation\MinValue;
use Hyperdrive\Http\Dto\Validation\NotEmpty;
use Hyperdrive\Http\Dto\Validation\ValidationException;
use PHPUnit\Framework\TestCase;

class TestValidatedDto extends Dto
{
    #[IsString]
    #[NotEmpty]
    #[MinLength(2)]
    public string $name;

    #[IsEmail]
    #[NotEmpty]
    public string $email;

    #[IsInt]
    #[MinValue(18)]
    public int $age;

    #[IsArray('string', ['admin', 'user', 'manager'])]
    #[NotEmpty]
    public array $roles = ['user'];
}

class ValidationTest extends TestCase
{
    public function test_it_validates_successfully_with_valid_data(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 25,
            'roles' => ['admin', 'user']
        ];

        $dto = new TestValidatedDto($data);

        $this->assertTrue($dto->isValid());
        $this->assertEquals('John Doe', $dto->name);
        $this->assertEquals('john@example.com', $dto->email);
        $this->assertEquals(25, $dto->age);
        $this->assertEquals(['admin', 'user'], $dto->roles);
    }

    public function test_it_throws_validation_exception_for_invalid_data(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Validation failed');

        $data = [
            'name' => 'J', // Too short
            'email' => 'invalid-email',
            'age' => 16, // Too young
            'roles' => ['invalid-role'] // Not in allowed values
        ];

        new TestValidatedDto($data);
    }

    public function test_it_collects_all_validation_errors(): void
    {
        try {
            $data = [
                'name' => '', // Empty - fails NotEmpty
                'email' => 'invalid', // Invalid email - fails IsEmail
                'age' => 16, // Valid int but fails MinValue(18)
                'roles' => ['invalid-role'] // Valid array but fails valueIn constraint
            ];

            new TestValidatedDto($data);
            $this->fail('Expected ValidationException was not thrown');
        } catch (ValidationException $e) {
            $errors = $e->getErrors();

            $this->assertArrayHasKey('name', $errors);
            $this->assertArrayHasKey('email', $errors);
            $this->assertArrayHasKey('age', $errors);
            $this->assertArrayHasKey('roles', $errors);

            $this->assertContains('Cannot be empty', $errors['name']);
            $this->assertContains('Must be a valid email address', $errors['email']);
            $this->assertContains('Must be at least 18', $errors['age']);
            $this->assertContains('Must be an array of strings with values in: admin, user, manager', $errors['roles']);
        }
    }

    public function test_it_handles_missing_optional_fields(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 25,
            // roles is optional and has default value
        ];

        $dto = new TestValidatedDto($data);

        $this->assertTrue($dto->isValid());
        $this->assertEquals(['user'], $dto->roles); // Should use default
    }

    public function test_it_ignores_extra_fields_not_defined_in_dto(): void
    {
        $data = [
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'age' => 25,
            'extra_field' => 'should be ignored',
            'another_extra' => 123
        ];

        $dto = new TestValidatedDto($data);

        $this->assertTrue($dto->isValid());
        $this->assertFalse(property_exists($dto, 'extra_field'));
        $this->assertFalse(property_exists($dto, 'another_extra'));
    }
}
