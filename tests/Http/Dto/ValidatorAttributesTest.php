<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Http\Dto;

use Hyperdrive\Http\Dto\Validation\IsArray;
use Hyperdrive\Http\Dto\Validation\IsEmail;
use Hyperdrive\Http\Dto\Validation\IsInt;
use Hyperdrive\Http\Dto\Validation\IsString;
use Hyperdrive\Http\Dto\Validation\MinLength;
use Hyperdrive\Http\Dto\Validation\MinValue;
use Hyperdrive\Http\Dto\Validation\NotEmpty;
use PHPUnit\Framework\TestCase;

class ValidatorAttributesTest extends TestCase
{
    public function test_is_string_validator(): void
    {
        $validator = new IsString();

        $this->assertTrue($validator->validate('hello', 'field'));
        $this->assertFalse($validator->validate(123, 'field'));
        $this->assertFalse($validator->validate([], 'field'));
        $this->assertEquals('Must be a string', $validator->getMessage());
    }

    public function test_not_empty_validator(): void
    {
        $validator = new NotEmpty();

        $this->assertTrue($validator->validate('hello', 'field'));
        $this->assertTrue($validator->validate(123, 'field'));
        $this->assertFalse($validator->validate('', 'field'));
        $this->assertFalse($validator->validate([], 'field'));
        $this->assertFalse($validator->validate(null, 'field'));
        $this->assertEquals('Cannot be empty', $validator->getMessage());
    }

    public function test_min_length_validator(): void
    {
        $validator = new MinLength(5);

        $this->assertTrue($validator->validate('hello', 'field'));
        $this->assertTrue($validator->validate('hello world', 'field'));
        $this->assertFalse($validator->validate('hi', 'field'));
        $this->assertFalse($validator->validate('', 'field'));
        $this->assertFalse($validator->validate(12345, 'field')); // Not a string
        $this->assertEquals('Must be at least 5 characters', $validator->getMessage());
    }

    public function test_is_email_validator(): void
    {
        $validator = new IsEmail();

        $this->assertTrue($validator->validate('test@example.com', 'field'));
        $this->assertTrue($validator->validate('user.name+tag@domain.co.uk', 'field'));
        $this->assertFalse($validator->validate('invalid-email', 'field'));
        $this->assertFalse($validator->validate('@example.com', 'field'));
        $this->assertFalse($validator->validate(123, 'field'));
        $this->assertEquals('Must be a valid email address', $validator->getMessage());
    }

    public function test_is_int_validator(): void
    {
        $validator = new IsInt();

        $this->assertTrue($validator->validate(123, 'field'));
        $this->assertTrue($validator->validate(0, 'field'));
        $this->assertTrue($validator->validate(-5, 'field'));
        $this->assertFalse($validator->validate('123', 'field'));
        $this->assertFalse($validator->validate(12.3, 'field'));
        $this->assertFalse($validator->validate([], 'field'));
        $this->assertEquals('Must be an integer', $validator->getMessage());
    }

    public function test_min_value_validator(): void
    {
        $validator = new MinValue(18);

        $this->assertTrue($validator->validate(18, 'field'));
        $this->assertTrue($validator->validate(25, 'field'));
        $this->assertFalse($validator->validate(17, 'field'));
        $this->assertFalse($validator->validate(0, 'field'));
        $this->assertFalse($validator->validate('18', 'field')); // Not an int
        $this->assertEquals('Must be at least 18', $validator->getMessage());
    }

    public function test_is_array_validator(): void
    {
        $validator = new IsArray();

        $this->assertTrue($validator->validate([], 'field'));
        $this->assertTrue($validator->validate([1, 2, 3], 'field'));
        $this->assertFalse($validator->validate('not-array', 'field'));
        $this->assertFalse($validator->validate(123, 'field'));
        $this->assertEquals('Must be an array', $validator->getMessage());
    }

    public function test_is_array_validator_with_type_constraint(): void
    {
        $validator = new IsArray('string');

        $this->assertTrue($validator->validate(['a', 'b', 'c'], 'field'));
        $this->assertFalse($validator->validate([1, 2, 3], 'field'));
        $this->assertFalse($validator->validate(['a', 2, 'c'], 'field'));
        $this->assertEquals('Must be an array of strings', $validator->getMessage());
    }

    public function test_is_array_validator_with_value_in_constraint(): void
    {
        $validator = new IsArray(null, ['admin', 'user']);

        $this->assertTrue($validator->validate(['admin'], 'field'));
        $this->assertTrue($validator->validate(['user', 'admin'], 'field'));
        $this->assertFalse($validator->validate(['invalid'], 'field'));
        $this->assertFalse($validator->validate(['admin', 'invalid'], 'field'));
        $this->assertEquals('Must be an array with values in: admin, user', $validator->getMessage());
    }

    public function test_is_array_validator_with_both_constraints(): void
    {
        $validator = new IsArray('string', ['admin', 'user']);

        $this->assertTrue($validator->validate(['admin'], 'field'));
        $this->assertFalse($validator->validate([1], 'field')); // Wrong type
        $this->assertFalse($validator->validate(['invalid'], 'field')); // Wrong value
        $this->assertEquals('Must be an array of strings with values in: admin, user', $validator->getMessage());
    }
}
