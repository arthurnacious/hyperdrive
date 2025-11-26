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
        public readonly ?string $content = null,
        public readonly array $injected = []
    ) {}

    public static function createFromSwoole(\OpenSwoole\Http\Request $swooleRequest): self
    {
        $server = [];
        foreach ($swooleRequest->server as $key => $value) {
            $server[strtoupper($key)] = $value;
        }

        // ✅ CRITICAL: Add headers for content type detection
        foreach ($swooleRequest->header as $key => $value) {
            $server['HTTP_' . strtoupper(str_replace('-', '_', $key))] = $value;
            // Also set standard headers for getContentType() method
            if (strtolower($key) === 'content-type') {
                $server['CONTENT_TYPE'] = $value;
            }
        }

        $files = [];
        if (!empty($swooleRequest->files)) {
            $files = self::normalizeSwooleFiles($swooleRequest->files);
        }

        return new self(
            query: $swooleRequest->get ?? [],
            request: $swooleRequest->post ?? [], // This will be empty for JSON
            attributes: [],
            cookies: $swooleRequest->cookie ?? [],
            files: $files,
            server: $server,
            content: $swooleRequest->rawContent() ?: null, // ✅ JSON goes here
            injected: []
        );
    }

    public static function createFromGlobals(): self
    {
        return new self(
            $_GET,
            $_POST,
            [],
            $_COOKIE,
            $_FILES,
            $_SERVER,
            file_get_contents('php://input'),
            []
        );
    }

    public function withInjected(string $key, mixed $value): self
    {
        $injected = $this->injected;
        $injected[$key] = $value;

        return new self(
            $this->query,
            $this->request,
            $this->attributes,
            $this->cookies,
            $this->files,
            $this->server,
            $this->content,
            $injected
        );
    }

    public function injected(string $key, mixed $default = null): mixed
    {
        return $this->injected[$key] ?? $default;
    }

    public function hasInjected(string $key): bool
    {
        return array_key_exists($key, $this->injected);
    }

    public function allInjected(): array
    {
        return $this->injected;
    }

    public function __get(string $name): mixed
    {
        $inputValue = $this->input($name);
        if ($inputValue !== null) {
            return $inputValue;
        }
        return $this->injected($name);
    }

    public function __isset(string $name): bool
    {
        return $this->has($name) || $this->hasInjected($name);
    }

    public function file(string $key): ?array
    {
        if (!isset($this->files[$key])) {
            return null;
        }

        $fileInfo = $this->files[$key];

        if (isset($fileInfo['name']) && !is_array($fileInfo['name'])) {
            return [new UploadedFile($fileInfo)];
        }

        return $this->normalizeFileArray($fileInfo);
    }

    public function firstFile(string $key): ?UploadedFile
    {
        $files = $this->file($key);
        return $files[0] ?? null;
    }

    public function hasFile(string $key): bool
    {
        $files = $this->file($key);
        return $files !== null && count($files) > 0;
    }

    public function allFiles(): array
    {
        $files = [];
        foreach (array_keys($this->files) as $key) {
            $files[$key] = $this->file($key) ?? [];
        }
        return $files;
    }

    public function all(): array
    {
        return array_merge($this->query, $this->getBody(), $this->attributes);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->getByDotNotation($this->all(), $key, $default);
    }

    public function query(string $key, mixed $default = null): mixed
    {
        return $this->getByDotNotation($this->query, $key, $default);
    }

    public function json(string $key, mixed $default = null): mixed
    {
        return $this->getByDotNotation($this->getBody(), $key, $default);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->input($key, $default);
    }

    public function has(string $key): bool
    {
        return $this->input($key) !== null;
    }

    public function only(array $keys): array
    {
        $all = $this->all();
        return array_intersect_key($all, array_flip($keys));
    }

    public function except(array $keys): array
    {
        $all = $this->all();
        return array_diff_key($all, array_flip($keys));
    }

    public function getMethod(): string
    {
        return $this->server['REQUEST_METHOD'] ?? $this->server['request_method'] ?? 'GET';
    }

    public function getPath(): string
    {
        $requestUri = $this->server['REQUEST_URI'] ?? $this->server['request_uri'] ?? '/';
        $path = parse_url($requestUri, PHP_URL_PATH) ?? '/';
        return $path === '' ? '/' : $path;
    }

    public function getContentType(): ?string
    {
        return $this->server['CONTENT_TYPE'] ?? null;
    }

    public function getBody(): array
    {
        $contentType = $this->getContentType();

        if (str_contains($contentType ?? '', 'application/json')) {
            $json = $this->content ?? '{}';
            try {
                return json_decode($json, true) ?? [];
            } catch (\JsonException $e) {
                return [];
            }
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
            $this->content,
            $this->injected
        );
    }

    public function getAttribute(string $key, mixed $default = null): mixed
    {
        return $this->attributes[$key] ?? $default;
    }

    private function normalizeFileArray(array $fileInfo): array
    {
        $files = [];
        $count = count($fileInfo['name']);

        for ($i = 0; $i < $count; $i++) {
            $files[] = new UploadedFile([
                'name' => $fileInfo['name'][$i],
                'type' => $fileInfo['type'][$i],
                'tmp_name' => $fileInfo['tmp_name'][$i],
                'error' => $fileInfo['error'][$i],
                'size' => $fileInfo['size'][$i]
            ]);
        }

        return $files;
    }

    private function getByDotNotation(array $data, string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $data)) {
            return $data[$key];
        }

        $current = $data;
        foreach (explode('.', $key) as $segment) {
            if (!is_array($current) || !array_key_exists($segment, $current)) {
                return $default;
            }
            $current = $current[$segment];
        }

        return $current;
    }

    private static function normalizeSwooleFiles(array $swooleFiles): array
    {
        $files = [];

        foreach ($swooleFiles as $key => $file) {
            if (isset($file['name']) && !is_array($file['name'])) {
                $files[$key] = $file;
            } else {
                $files[$key] = self::rearrangeSwooleFileArray($file);
            }
        }

        return $files;
    }

    private static function rearrangeSwooleFileArray(array $file): array
    {
        $rearranged = [];

        if (!isset($file['name']) || !is_array($file['name'])) {
            return [$file];
        }

        $count = count($file['name']);

        for ($i = 0; $i < $count; $i++) {
            $rearranged[] = [
                'name' => $file['name'][$i],
                'type' => $file['type'][$i],
                'tmp_name' => $file['tmp_name'][$i],
                'error' => $file['error'][$i],
                'size' => $file['size'][$i]
            ];
        }

        return $rearranged;
    }
}
