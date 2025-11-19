<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Http;

use Hyperdrive\Http\JsonResponse;
use Hyperdrive\Http\Response;
use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase
{
    public function test_it_creates_json_response(): void
    {
        $data = ['message' => 'Success', 'user' => ['id' => 123]];
        $response = JsonResponse::json($data, 201);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaders()['Content-Type']);
        $this->assertEquals(json_encode($data), $response->getContent());
    }

    public function test_json_response_constructor(): void
    {
        $data = ['status' => 'ok'];
        $response = new JsonResponse($data, 200, ['X-Custom' => 'value']);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaders()['Content-Type']);
        $this->assertEquals('value', $response->getHeaders()['X-Custom']);
        $this->assertEquals(json_encode($data), $response->getContent());
    }
}
