<?php

declare(strict_types=1);

namespace Hyperdrive\Http\Dto\Validation;

use Hyperdrive\Http\JsonResponse;

class ValidationException extends \Exception
{
    public function __construct(private array $errors)
    {
        parent::__construct('Validation failed', 422);
    }

    public function getErrorResponse(): JsonResponse
    {
        return new JsonResponse([
            'error' => 'Validation failed',
            'errors' => $this->errors
        ], 422);
    }

    public function getErrors(): array
    {
        return $this->errors;
    }
}
