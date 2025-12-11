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

    /**
     * Create response with HTTP-only cookie
     */
    protected function withCookie(
        mixed $data = null,
        string $cookieName = 'token',
        string $cookieValue = '',
        array $cookieOptions = [],
        int $status = 200,
        array $headers = []
    ): Response {
        $response = new Response(
            is_array($data) ? json_encode($data, JSON_THROW_ON_ERROR) : (string) $data,
            $status,
            array_merge(['Content-Type' => 'application/json'], $headers)
        );

        $defaultOptions = [
            'expires' => time() + 3600, // 1 hour
            'path' => '/',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ];

        $response->setCookie($cookieName, $cookieValue, array_merge($defaultOptions, $cookieOptions));

        return $response;
    }

    /**
     * Create JSON response with HTTP-only cookie
     */
    protected function jsonWithCookie(
        array $data,
        string $cookieName = 'token',
        string $cookieValue = '',
        array $cookieOptions = [],
        int $status = 200,
        array $headers = []
    ): Response {
        return $this->withCookie($data, $cookieName, $cookieValue, $cookieOptions, $status, $headers);
    }

    /**
     * Remove cookie
     */
    protected function removeCookie(
        string $cookieName = 'token',
        ?array $data = null,
        int $status = 200,
        array $headers = []
    ): Response {
        $response = $data
            ? new Response(json_encode($data, JSON_THROW_ON_ERROR), $status, array_merge(['Content-Type' => 'application/json'], $headers))
            : new Response('', $status, $headers);

        $response->removeCookie($cookieName);
        return $response;
    }
}
