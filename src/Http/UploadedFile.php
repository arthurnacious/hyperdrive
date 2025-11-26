<?php

declare(strict_types=1);

namespace Hyperdrive\Http;

class UploadedFile
{
    public function __construct(
        private array $fileInfo
    ) {}

    public function getClientOriginalName(): string
    {
        return $this->fileInfo['name'] ?? '';
    }

    public function getClientOriginalExtension(): string
    {
        return pathinfo($this->getClientOriginalName(), PATHINFO_EXTENSION);
    }

    public function getClientMimeType(): string
    {
        return $this->fileInfo['type'] ?? '';
    }

    public function getSize(): int
    {
        return $this->fileInfo['size'] ?? 0;
    }

    public function getError(): int
    {
        return $this->fileInfo['error'] ?? UPLOAD_ERR_NO_FILE;
    }

    public function getPathname(): string
    {
        return $this->fileInfo['tmp_name'] ?? '';
    }

    public function isValid(): bool
    {
        return $this->getError() === UPLOAD_ERR_OK;
    }

    /**
     * Move to local filesystem
     */
    public function move(string $directory, ?string $name = null): string
    {
        return (new FileUploader())->move($this, $directory, $name);
    }

    /**
     * Get file content for external storage
     */
    public function getContent(): string
    {
        return file_get_contents($this->getPathname());
    }

    /**
     * Get file as resource for streaming
     */
    public function getResource()
    {
        return fopen($this->getPathname(), 'r');
    }
}
