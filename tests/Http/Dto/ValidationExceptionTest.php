<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Http\Dto;

use Hyperdrive\Http\Dto\Validation\ValidationException;
use Hyperdrive\Http\JsonResponse;
use PHPUnit\Framework\TestCase;

class ValidationExceptionTest extends TestCase
{
    public function test_it_creates_validation_exception_with_errors(): void
    {
        $errors = [
            'name' => ['Cannot be empty'],
            'email' => ['Must be a valid email address'],
            'age' => ['Must be at least 18']
        ];

        $exception = new ValidationException($errors);

        $this->assertEquals('Validation failed', $exception->getMessage());
        $this->assertEquals(422, $exception->getCode());
        $this->assertEquals($errors, $exception->getErrors());
    }

    public function test_it_creates_error_response(): void
    {
        $errors = [
            'name' => ['Cannot be empty'],
            'email' => ['Must be a valid email address']
        ];

        $exception = new ValidationException($errors);
        $response = $exception->getErrorResponse();

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertEquals(422, $response->getStatusCode());

        $content = json_decode($response->getContent(), true);
        $this->assertEquals('Validation failed', $content['error']);
        $this->assertEquals($errors, $content['errors']);
    }
}
