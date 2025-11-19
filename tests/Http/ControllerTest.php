<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Http;

use Hyperdrive\Http\Controller;
use Hyperdrive\Http\JsonResponse;
use PHPUnit\Framework\TestCase;

class TestController extends Controller
{
    public function getOk(): JsonResponse
    {
        return $this->ok(['message' => 'success']);
    }

    public function getCreated(): JsonResponse
    {
        return $this->created(['id' => 123]);
    }

    public function getNoContent(): JsonResponse
    {
        return $this->noContent();
    }

    public function getNotFound(): JsonResponse
    {
        return $this->notFound('Resource not found');
    }

    public function getServerError(): JsonResponse
    {
        return $this->serverError('Something went wrong');
    }

    public function getCustomError(): JsonResponse
    {
        return $this->error('Custom error', 418);
    }
}

class ControllerTest extends TestCase
{
    public function test_ok_response(): void
    {
        $controller = new TestController();
        $response = $controller->getOk();

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals(['message' => 'success'], json_decode($response->getContent(), true));
    }

    public function test_created_response(): void
    {
        $controller = new TestController();
        $response = $controller->getCreated();

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals(['id' => 123], json_decode($response->getContent(), true));
    }

    public function test_no_content_response(): void
    {
        $controller = new TestController();
        $response = $controller->getNoContent();

        $this->assertEquals(204, $response->getStatusCode());
        $this->assertEquals('', $response->getContent());
    }

    public function test_not_found_response(): void
    {
        $controller = new TestController();
        $response = $controller->getNotFound();

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals(['error' => 'Resource not found'], json_decode($response->getContent(), true));
    }

    public function test_server_error_response(): void
    {
        $controller = new TestController();
        $response = $controller->getServerError();

        $this->assertEquals(500, $response->getStatusCode());
        $this->assertEquals(['error' => 'Something went wrong'], json_decode($response->getContent(), true));
    }

    public function test_custom_error_response(): void
    {
        $controller = new TestController();
        $response = $controller->getCustomError();

        $this->assertEquals(418, $response->getStatusCode());
        $this->assertEquals(['error' => 'Custom error'], json_decode($response->getContent(), true));
    }
}
