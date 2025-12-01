<?php
// src/Http/Response.php

declare(strict_types=1);

namespace Hyperdrive\Http;

class Response
{
    public function __construct(
        private mixed $content = '',
        private int $status = 200,
        private array $headers = []
    ) {}

    public static function json(array $data, int $status = 200, array $headers = []): self
    {
        $headers['Content-Type'] = 'application/json';

        return new self(
            json_encode($data, JSON_THROW_ON_ERROR),
            $status,
            $headers
        );
    }

    public static function file(string $fileContent, string $filename, string $contentType = 'application/octet-stream'): self
    {
        return new self(
            $fileContent,
            200,
            [
                'Content-Type' => $contentType,
                'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
                'Content-Length' => strlen($fileContent),
            ]
        );
    }

    public static function pdf(string $pdfContent, string $filename = 'document.pdf'): self
    {
        return self::file($pdfContent, $filename, 'application/pdf');
    }

    public static function stream($resource, string $filename, string $contentType = 'application/octet-stream'): self
    {
        // For streaming large files
        return new StreamedResponse($resource, $filename, $contentType);
    }

    public function getContent(): string
    {
        return (string) $this->content;
    }

    public function getRawContent(): mixed
    {
        return $this->content;
    }

    public function getStatusCode(): int
    {
        return $this->status;
    }

    public function getHeaders(): array
    {
        return $this->headers;
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header("$name: $value");
        }

        // Handle binary content properly
        if (is_resource($this->content)) {
            fpassthru($this->content);
            fclose($this->content);
        } else {
            echo $this->content;
        }
    }
}
