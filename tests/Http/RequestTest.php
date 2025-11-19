<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Http;

use Hyperdrive\Http\Request;
use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase
{
    public function test_it_can_create_from_globals(): void
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_SERVER['REQUEST_URI'] = '/users';
        $_SERVER['CONTENT_TYPE'] = 'application/json';

        $request = Request::createFromGlobals();

        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('/users', $request->getPath());
        $this->assertEquals('application/json', $request->getContentType());
    }

    public function test_it_can_add_and_retrieve_attributes(): void
    {
        $request = new Request();

        $newRequest = $request->withAttribute('user_sub', 'user_123');
        $newRequest = $newRequest->withAttribute('tenant_id', 'tenant_456');

        $this->assertEquals('user_123', $newRequest->getAttribute('user_sub'));
        $this->assertEquals('tenant_456', $newRequest->getAttribute('tenant_id'));
        $this->assertNull($request->getAttribute('user_sub')); // Original unchanged
    }

    public function test_it_parses_json_body(): void
    {
        $jsonData = ['name' => 'John', 'email' => 'john@example.com'];
        $content = json_encode($jsonData);

        $request = new Request(
            server: ['CONTENT_TYPE' => 'application/json'],
            content: $content
        );

        $this->assertEquals($jsonData, $request->getBody());
    }

    public function test_it_handles_form_data_body(): void
    {
        $formData = ['name' => 'John', 'email' => 'john@example.com'];

        $request = new Request(
            request: $formData,
            server: ['CONTENT_TYPE' => 'application/x-www-form-urlencoded']
        );

        $this->assertEquals($formData, $request->getBody());
    }
}
