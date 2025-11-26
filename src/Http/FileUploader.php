<?php

declare(strict_types=1);

namespace Hyperdrive\Http;

class FileUploader
{
    /**
     * Move uploaded file to destination
     */
    public function move(UploadedFile $file, string $directory, ?string $name = null): string
    {
        if (!$file->isValid()) {
            throw new \RuntimeException('Cannot move invalid file');
        }

        // Ensure directory exists
        if (!is_dir($directory)) {
            mkdir($directory, 0777, true);
        }

        $target = $directory . '/' . ($name ?: $file->getClientOriginalName());
        $sourcePath = $file->getPathname();

        if (is_uploaded_file($sourcePath)) {
            // Real uploaded file - use move_uploaded_file
            if (!move_uploaded_file($sourcePath, $target)) {
                throw new \RuntimeException('Failed to move uploaded file');
            }
        } else {
            // Test file or regular file - use rename (which actually moves the file)
            if (!rename($sourcePath, $target)) {
                throw new \RuntimeException('Failed to move file');
            }
        }

        return $target;
    }

    /**
     * Get file content for streaming to external storage
     */
    public function getContent(UploadedFile $file): string
    {
        return $file->getContent();
    }

    /**
     * Get file as resource for streaming
     */
    public function getResource(UploadedFile $file)
    {
        return fopen($file->getPathname(), 'r');
    }
}
