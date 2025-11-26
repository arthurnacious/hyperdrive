<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Http;

use Hyperdrive\Http\UploadedFile;
use PHPUnit\Framework\TestCase;

class UploadedFileTest extends TestCase
{
    private string $testDirectory;

    protected function setUp(): void
    {
        $this->testDirectory = sys_get_temp_dir() . '/hyperdrive_test';
        if (!is_dir($this->testDirectory)) {
            mkdir($this->testDirectory, 0777, true);
        }
    }

    protected function tearDown(): void
    {
        if (is_dir($this->testDirectory)) {
            array_map('unlink', glob($this->testDirectory . '/*'));
            rmdir($this->testDirectory);
        }
    }

    public function test_it_returns_file_properties(): void
    {
        $fileInfo = [
            'name' => 'test.txt',
            'type' => 'text/plain',
            'tmp_name' => '/tmp/php123',
            'error' => UPLOAD_ERR_OK,
            'size' => 1024
        ];

        $file = new UploadedFile($fileInfo);

        $this->assertEquals('test.txt', $file->getClientOriginalName());
        $this->assertEquals('txt', $file->getClientOriginalExtension());
        $this->assertEquals('text/plain', $file->getClientMimeType());
        $this->assertEquals(1024, $file->getSize());
        $this->assertEquals(UPLOAD_ERR_OK, $file->getError());
        $this->assertEquals('/tmp/php123', $file->getPathname());
    }

    public function test_it_validates_file(): void
    {
        $validFile = new UploadedFile([
            'name' => 'test.txt',
            'type' => 'text/plain',
            'tmp_name' => '/tmp/php123',
            'error' => UPLOAD_ERR_OK,
            'size' => 1024
        ]);

        $invalidFile = new UploadedFile([
            'name' => 'test.txt',
            'type' => 'text/plain',
            'tmp_name' => '/tmp/php123',
            'error' => UPLOAD_ERR_NO_FILE,
            'size' => 0
        ]);

        $this->assertTrue($validFile->isValid());
        $this->assertFalse($invalidFile->isValid());
    }

    public function test_it_moves_file_using_uploader(): void
    {
        $sourceFile = $this->createTestFile('source.txt', 'Test content');
        $file = new UploadedFile([
            'name' => 'original.txt',
            'type' => 'text/plain',
            'tmp_name' => $sourceFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($sourceFile)
        ]);

        $targetPath = $file->move($this->testDirectory, 'moved.txt');

        $this->assertEquals($this->testDirectory . '/moved.txt', $targetPath);
        $this->assertFileExists($targetPath);
        $this->assertEquals('Test content', file_get_contents($targetPath));
        $this->assertFileDoesNotExist($sourceFile);
    }

    public function test_it_gets_file_content(): void
    {
        $sourceFile = $this->createTestFile('source.txt', 'File content');
        $file = new UploadedFile([
            'name' => 'test.txt',
            'type' => 'text/plain',
            'tmp_name' => $sourceFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($sourceFile)
        ]);

        $content = $file->getContent();

        $this->assertEquals('File content', $content);
    }

    public function test_it_gets_file_resource(): void
    {
        $sourceFile = $this->createTestFile('source.txt', 'Resource content');
        $file = new UploadedFile([
            'name' => 'test.txt',
            'type' => 'text/plain',
            'tmp_name' => $sourceFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($sourceFile)
        ]);

        $resource = $file->getResource();

        $this->assertIsResource($resource);
        $this->assertEquals('Resource content', stream_get_contents($resource));
        fclose($resource);
    }

    private function createTestFile(string $filename, string $content): string
    {
        $path = $this->testDirectory . '/' . $filename;
        file_put_contents($path, $content);
        return $path;
    }
}
