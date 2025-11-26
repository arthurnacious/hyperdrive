<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Http;

use Hyperdrive\Http\FileUploader;
use Hyperdrive\Http\UploadedFile;
use PHPUnit\Framework\TestCase;

class FileUploaderTest extends TestCase
{
    private string $testDirectory;
    private string $sourceDirectory;
    private FileUploader $uploader;

    protected function setUp(): void
    {
        $this->testDirectory = sys_get_temp_dir() . '/hyperdrive_test_dest_' . uniqid();
        $this->sourceDirectory = sys_get_temp_dir() . '/hyperdrive_test_src_' . uniqid();

        foreach ([$this->testDirectory, $this->sourceDirectory] as $dir) {
            if (!is_dir($dir)) {
                mkdir($dir, 0777, true);
            }
        }

        $this->uploader = new FileUploader();
    }

    protected function tearDown(): void
    {
        // Clean up test directories recursively
        $this->deleteDirectory($this->testDirectory);
        $this->deleteDirectory($this->sourceDirectory);
    }

    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->deleteDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    public function test_it_moves_uploaded_file(): void
    {
        $sourceFile = $this->createTestFile($this->sourceDirectory, 'source.txt', 'Test content');
        $file = new UploadedFile([
            'name' => 'test.txt',
            'type' => 'text/plain',
            'tmp_name' => $sourceFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($sourceFile)
        ]);

        $targetPath = $this->uploader->move($file, $this->testDirectory, 'moved.txt');

        $this->assertEquals($this->testDirectory . '/moved.txt', $targetPath);
        $this->assertFileExists($targetPath);
        $this->assertEquals('Test content', file_get_contents($targetPath));
        $this->assertFileDoesNotExist($sourceFile);
    }

    public function test_it_uses_original_filename_when_no_name_provided(): void
    {
        $sourceFile = $this->createTestFile($this->sourceDirectory, 'source.txt', 'Test content');
        $file = new UploadedFile([
            'name' => 'original.txt',
            'type' => 'text/plain',
            'tmp_name' => $sourceFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($sourceFile)
        ]);

        $targetPath = $this->uploader->move($file, $this->testDirectory);

        $this->assertEquals($this->testDirectory . '/original.txt', $targetPath);
        $this->assertFileExists($targetPath);
        $this->assertFileDoesNotExist($sourceFile);
    }

    public function test_it_throws_exception_for_invalid_file(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Cannot move invalid file');

        $file = new UploadedFile([
            'name' => 'test.txt',
            'type' => 'text/plain',
            'tmp_name' => '',
            'error' => UPLOAD_ERR_NO_FILE,
            'size' => 0
        ]);

        $this->uploader->move($file, $this->testDirectory);
    }

    public function test_it_gets_file_content(): void
    {
        $sourceFile = $this->createTestFile($this->sourceDirectory, 'source.txt', 'File content for testing');
        $file = new UploadedFile([
            'name' => 'test.txt',
            'type' => 'text/plain',
            'tmp_name' => $sourceFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($sourceFile)
        ]);

        $content = $this->uploader->getContent($file);

        $this->assertEquals('File content for testing', $content);
        $this->assertFileExists($sourceFile);
    }

    public function test_it_gets_file_resource(): void
    {
        $sourceFile = $this->createTestFile($this->sourceDirectory, 'source.txt', 'Resource content');
        $file = new UploadedFile([
            'name' => 'test.txt',
            'type' => 'text/plain',
            'tmp_name' => $sourceFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($sourceFile)
        ]);

        $resource = $this->uploader->getResource($file);

        $this->assertIsResource($resource);
        $this->assertEquals('Resource content', stream_get_contents($resource));
        fclose($resource);
        $this->assertFileExists($sourceFile);
    }

    public function test_it_creates_destination_directory_if_not_exists(): void
    {
        $newDirectory = $this->testDirectory . '/subdir_' . uniqid();
        $sourceFile = $this->createTestFile($this->sourceDirectory, 'source.txt', 'Test content');
        $file = new UploadedFile([
            'name' => 'test.txt',
            'type' => 'text/plain',
            'tmp_name' => $sourceFile,
            'error' => UPLOAD_ERR_OK,
            'size' => filesize($sourceFile)
        ]);

        $this->assertDirectoryDoesNotExist($newDirectory);

        $targetPath = $this->uploader->move($file, $newDirectory, 'moved.txt');

        $this->assertDirectoryExists($newDirectory);
        $this->assertFileExists($targetPath);
        $this->assertEquals('Test content', file_get_contents($targetPath));
    }

    private function createTestFile(string $directory, string $filename, string $content): string
    {
        $path = $directory . '/' . $filename;
        file_put_contents($path, $content);
        return $path;
    }
}
