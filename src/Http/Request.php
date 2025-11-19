<?php

declare(strict_types=1);

namespace Hyperdrive\Http;

class Request
{
    public function __construct(
        public readonly array $query = [],
        public readonly array $request = [],
        public readonly array $attributes = [],
        public readonly array $cookies = [],
        public readonly array $files = [],
        public readonly array $server = [],
        public readonly ?string $content = null
    ) {}

    public static function createFromGlobals(): self
    {
        return new self(
            $_GET,
            $_POST,
            [],
            $_COOKIE,
            $_FILES,
            $_SERVER,
            file_get_contents('php://input')
        );
    }

    public function getMethod(): string
    {
        return $this->server['REQUEST_METHOD'] ?? 'GET';
    }

    public function getPath(): string
    {
        return parse_url($this->server['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
    }

    public function getContentType(): ?string
    {
        return $this->server['CONTENT_TYPE'] ?? null;
    }

    public function getBody(): array
    {
        $contentType = $this->getContentType();

        if (str_contains($contentType ?? '', 'application/json')) {
            return json_decode($this->content ?? '{}', true) ?? [];
        }

        return $this->request;
    }

    public function withAttribute(string $key, mixed $value): self
    {
        $attributes = $this->attributes;
        $attributes[$key] = $value;

        return new self(
            $this->query,
            $this->request,
            $attributes,
            $this->cookies,
            $this->files,
            $this->server,
            $this->content
        );
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }
}
