<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Http;

use Hyperdrive\Http\Request;
use Hyperdrive\Http\UploadedFile;
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

    public function test_it_handles_file_uploads(): void
    {
        $files = [
            'avatar' => [
                'name' => 'test.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '/tmp/php123',
                'error' => UPLOAD_ERR_OK,
                'size' => 1024
            ]
        ];

        $request = new Request(files: $files);

        $uploadedFiles = $request->file('avatar');
        $this->assertIsArray($uploadedFiles);
        $this->assertCount(1, $uploadedFiles);
        $this->assertInstanceOf(UploadedFile::class, $uploadedFiles[0]);
        $this->assertEquals('test.jpg', $uploadedFiles[0]->getClientOriginalName());
    }

    public function test_it_handles_multiple_file_uploads(): void
    {
        $files = [
            'documents' => [
                'name' => ['doc1.pdf', 'doc2.pdf'],
                'type' => ['application/pdf', 'application/pdf'],
                'tmp_name' => ['/tmp/php123', '/tmp/php456'],
                'error' => [UPLOAD_ERR_OK, UPLOAD_ERR_OK],
                'size' => [1024, 2048]
            ]
        ];

        $request = new Request(files: $files);

        $uploadedFiles = $request->file('documents');
        $this->assertIsArray($uploadedFiles);
        $this->assertCount(2, $uploadedFiles);
        $this->assertEquals('doc1.pdf', $uploadedFiles[0]->getClientOriginalName());
        $this->assertEquals('doc2.pdf', $uploadedFiles[1]->getClientOriginalName());
    }

    public function test_it_returns_first_file(): void
    {
        $files = [
            'avatar' => [
                'name' => 'test.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '/tmp/php123',
                'error' => UPLOAD_ERR_OK,
                'size' => 1024
            ]
        ];

        $request = new Request(files: $files);

        $firstFile = $request->firstFile('avatar');
        $this->assertInstanceOf(UploadedFile::class, $firstFile);
        $this->assertEquals('test.jpg', $firstFile->getClientOriginalName());
    }

    public function test_it_checks_if_file_exists(): void
    {
        $files = [
            'avatar' => [
                'name' => 'test.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '/tmp/php123',
                'error' => UPLOAD_ERR_OK,
                'size' => 1024
            ]
        ];

        $request = new Request(files: $files);

        $this->assertTrue($request->hasFile('avatar'));
        $this->assertFalse($request->hasFile('nonexistent'));
    }

    public function test_it_gets_all_files(): void
    {
        $files = [
            'avatar' => [
                'name' => 'test.jpg',
                'type' => 'image/jpeg',
                'tmp_name' => '/tmp/php123',
                'error' => UPLOAD_ERR_OK,
                'size' => 1024
            ],
            'documents' => [
                'name' => ['doc1.pdf'],
                'type' => ['application/pdf'],
                'tmp_name' => ['/tmp/php456'],
                'error' => [UPLOAD_ERR_OK],
                'size' => [2048]
            ]
        ];

        $request = new Request(files: $files);

        $allFiles = $request->allFiles();
        $this->assertArrayHasKey('avatar', $allFiles);
        $this->assertArrayHasKey('documents', $allFiles);
        $this->assertCount(1, $allFiles['avatar']);
        $this->assertCount(1, $allFiles['documents']);
    }
}
