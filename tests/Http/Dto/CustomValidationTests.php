<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Http\Dto;

use Hyperdrive\Http\Dto;
use Hyperdrive\Http\Dto\Validation\IsEmail;
use Hyperdrive\Http\Dto\Validation\IsString;
use Hyperdrive\Http\Dto\Validation\MinLength;
use Hyperdrive\Http\Dto\Validation\ValidateWith;
use Hyperdrive\Http\Dto\Validation\ValidationException;
use Hyperdrive\Http\Request;
use PHPUnit\Framework\TestCase;

class TestUserDto extends Dto
{
    #[IsString]
    #[MinLength(2)]
    #[ValidateWith('validateName')]
    public string $name;

    #[IsEmail]
    #[ValidateWith('validateEmail')]
    public string $email;

    #[IsString]
    #[MinLength(8)]
    #[ValidateWith('validatePassword')]
    public string $password;

    private function validateName(string $value): void
    {
        if ($value === 'admin') {
            $this->addError('name', 'Username "admin" is reserved');
        }

        if (str_contains($value, '@')) {
            $this->addError('name', 'Username cannot contain @ symbol');
        }
    }

    private function validateEmail(string $email, string $field): void
    {
        $domain = explode('@', $email)[1] ?? '';

        if ($domain === 'competitor.com') {
            $this->addError($field, 'Competitor emails are not allowed');
        }
    }

    private function validatePassword(string $password): void
    {
        if (!preg_match('/[A-Z]/', $password)) {
            $this->addError('password', 'Add at least one uppercase letter');
        }

        if (!preg_match('/[0-9]/', $password)) {
            $this->addError('password', 'Add at least one number');
        }
    }

    private function validateAll(): void
    {
        if ($this->name === 'test' && $this->password === 'test123') {
            $this->addError('password', 'Test accounts require stronger passwords');
        }
    }
}

class TestContextAwareDto extends Dto
{
    #[IsString]
    #[ValidateWith('validateWithContext')]
    public string $data;

    private function validateWithContext(string $value): void
    {
        $request = $this->getContext('request');
        if ($request && $request->getClientIp() === '192.168.1.1') {
            $this->addError('data', 'Blocked IP address');
        }
    }
}

class CustomDtoValidationTest extends TestCase
{
    public function test_it_handles_custom_field_validations_with_value_parameter(): void
    {
        $data = [
            'name' => 'test',
            'email' => 'test@example.com',
            'password' => 'weak'
        ];

        $dto = new TestUserDto($data, []);

        $this->assertFalse($dto->isValid());
        $errors = $dto->getErrors();

        $this->assertArrayHasKey('password', $errors);
        $this->assertContains('Add at least one uppercase letter', $errors['password']);
        $this->assertContains('Add at least one number', $errors['password']);
    }

    public function test_it_handles_custom_validations_with_field_parameter(): void
    {
        $data = [
            'name' => 'valid',
            'email' => 'user@competitor.com',
            'password' => 'Strong123'
        ];

        $dto = new TestUserDto($data, []);

        $this->assertFalse($dto->isValid());
        $errors = $dto->getErrors();

        $this->assertArrayHasKey('email', $errors);
        $this->assertContains('Competitor emails are not allowed', $errors['email']);
    }

    public function test_it_handles_reserved_username_validation(): void
    {
        $data = [
            'name' => 'admin',
            'email' => 'admin@example.com',
            'password' => 'Strong123'
        ];

        $dto = new TestUserDto($data, []);

        $this->assertFalse($dto->isValid());
        $errors = $dto->getErrors();

        $this->assertArrayHasKey('name', $errors);
        $this->assertContains('Username "admin" is reserved', $errors['name']);
    }

    public function test_it_handles_username_with_invalid_character(): void
    {
        $data = [
            'name' => 'user@name',
            'email' => 'user@example.com',
            'password' => 'Strong123'
        ];

        $dto = new TestUserDto($data, []);

        $this->assertFalse($dto->isValid());
        $errors = $dto->getErrors();

        $this->assertArrayHasKey('name', $errors);
        $this->assertContains('Username cannot contain @ symbol', $errors['name']);
    }

    public function test_it_passes_with_valid_data(): void
    {
        $data = [
            'name' => 'john_doe',
            'email' => 'john@example.com',
            'password' => 'StrongPassword123'
        ];

        $dto = new TestUserDto($data, []);

        $this->assertTrue($dto->isValid());
        $this->assertEmpty($dto->getErrors());
    }

    public function test_it_throws_validation_exception_with_all_errors(): void
    {
        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Validation failed');

        $data = [
            'name' => 'admin@name',
            'email' => 'admin@competitor.com',
            'password' => 'weak'
        ];

        new TestUserDto($data, []);
    }

    public function test_it_runs_post_validation_with_validate_all(): void
    {
        $data = [
            'name' => 'test',
            'email' => 'test@example.com',
            'password' => 'test123'
        ];

        $dto = new TestUserDto($data, []);

        $this->assertFalse($dto->isValid());
        $errors = $dto->getErrors();

        $this->assertArrayHasKey('password', $errors);
        $this->assertContains('Test accounts require stronger passwords', $errors['password']);
    }

    public function test_it_uses_context_in_validation(): void
    {
        $mockRequest = $this->createMock(Request::class);
        $mockRequest->method('getClientIp')->willReturn('192.168.1.1');

        $data = ['data' => 'test'];
        $context = ['request' => $mockRequest];

        $dto = new TestContextAwareDto($data, $context);

        $this->assertFalse($dto->isValid());
        $errors = $dto->getErrors();
        $this->assertContains('Blocked IP address', $errors['data']);
    }

    public function test_it_passes_with_allowed_context(): void
    {
        $mockRequest = $this->createMock(Request::class);
        $mockRequest->method('getClientIp')->willReturn('10.0.0.1');

        $data = ['data' => 'test'];
        $context = ['request' => $mockRequest];

        $dto = new TestContextAwareDto($data, $context);

        $this->assertTrue($dto->isValid());
        $this->assertEmpty($dto->getErrors());
    }

    public function test_it_can_add_multiple_errors_at_once(): void
    {
        $data = [
            'name' => 'a', // Too short for MinLength(2)
            'email' => 'test@example.com',
            'password' => 'weak'
        ];

        $dto = new TestUserDto($data, []);

        $this->assertFalse($dto->isValid());
        $errors = $dto->getErrors();

        $this->assertArrayHasKey('name', $errors);
        $this->assertContains('Must be at least 2 characters', $errors['name']);
        $this->assertArrayHasKey('password', $errors);
    }

    public function test_it_handles_add_error_helper_methods(): void
    {
        // Create a test DTO that uses the helper methods
        $data = [
            'name' => 'test',
            'email' => 'test@example.com',
            'password' => 'Strong123'
        ];

        $dto = new TestUserDto($data, []);

        // Simulate what would happen in a custom validation method
        $dto->addErrorIf('test_field', true, 'Condition is true');
        $dto->addErrorUnless('test_field', false, 'Condition is false');
        $dto->addError('another_field', 'Single error');
        $dto->addError('multi_field', ['Error 1', 'Error 2']);
        $dto->addError('variadic_field', 'Error A', 'Error B', ['Error C', 'Error D']);

        $errors = $dto->getErrors();

        $this->assertContains('Condition is true', $errors['test_field']);
        $this->assertContains('Condition is false', $errors['test_field']);
        $this->assertContains('Single error', $errors['another_field']);
        $this->assertContains('Error 1', $errors['multi_field']);
        $this->assertContains('Error 2', $errors['multi_field']);
        $this->assertContains('Error A', $errors['variadic_field']);
        $this->assertContains('Error B', $errors['variadic_field']);
        $this->assertContains('Error C', $errors['variadic_field']);
        $this->assertContains('Error D', $errors['variadic_field']);
    }

    public function test_it_combines_attribute_and_custom_validations(): void
    {
        $data = [
            'name' => '', // Fails MinLength(2)
            'email' => 'invalid-email', // Fails IsEmail
            'password' => 'weak' // Fails custom password validation
        ];

        $dto = new TestUserDto($data, []);

        $this->assertFalse($dto->isValid());
        $errors = $dto->getErrors();

        // Attribute validations
        $this->assertArrayHasKey('name', $errors);
        $this->assertContains('Must be at least 2 characters', $errors['name']);
        $this->assertArrayHasKey('email', $errors);
        $this->assertContains('Must be a valid email address', $errors['email']);

        // Custom validations
        $this->assertArrayHasKey('password', $errors);
        $this->assertContains('Add at least one uppercase letter', $errors['password']);
        $this->assertContains('Add at least one number', $errors['password']);
    }
}
