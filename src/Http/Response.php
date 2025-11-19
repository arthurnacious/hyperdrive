<?php

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

    public function getContent(): string
    {
        return (string) $this->content;
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

        echo $this->content;
    }
}
