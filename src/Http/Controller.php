<?php

declare(strict_types=1);

namespace Hyperdrive\Http;

abstract class Controller
{
    protected function ok(mixed $data = null, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, 200, $headers);
    }

    protected function created(mixed $data = null, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, 201, $headers);
    }

    protected function accepted(mixed $data = null, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, 202, $headers);
    }

    protected function noContent(array $headers = []): JsonResponse
    {
        return new JsonResponse([], 204, $headers);
    }

    protected function badRequest(string $message = 'Bad Request', array $headers = []): JsonResponse
    {
        return $this->error($message, 400, $headers);
    }

    protected function unauthorized(string $message = 'Unauthorized', array $headers = []): JsonResponse
    {
        return $this->error($message, 401, $headers);
    }

    protected function forbidden(string $message = 'Forbidden', array $headers = []): JsonResponse
    {
        return $this->error($message, 403, $headers);
    }

    protected function notFound(string $message = 'Not Found', array $headers = []): JsonResponse
    {
        return $this->error($message, 404, $headers);
    }

    protected function conflict(string $message = 'Conflict', array $headers = []): JsonResponse
    {
        return $this->error($message, 409, $headers);
    }

    protected function unprocessableEntity(string $message = 'Unprocessable Entity', array $headers = []): JsonResponse
    {
        return $this->error($message, 422, $headers);
    }

    protected function serverError(string $message = 'Internal Server Error', array $headers = []): JsonResponse
    {
        return $this->error($message, 500, $headers);
    }

    protected function error(string $message, int $status = 500, array $headers = []): JsonResponse
    {
        return new JsonResponse(['error' => $message], $status, $headers);
    }

    protected function json(mixed $data, int $status = 200, array $headers = []): JsonResponse
    {
        return new JsonResponse($data, $status, $headers);
    }
}
