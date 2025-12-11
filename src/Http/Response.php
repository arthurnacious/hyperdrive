<?php

declare(strict_types=1);

namespace Hyperdrive\Http;

class Response
{
    private array $cookies = [];

    public function __construct(
        private mixed $content = '',
        private int $status = 200,
        private array $headers = [],
        array $cookies = []
    ) {
        $this->setCookies($cookies);
    }

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

    /**
     * Set a cookie
     */
    public function setCookie(
        string $name,
        string $value = '',
        array $options = []
    ): self {
        $defaults = [
            'expires' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict'
        ];

        $this->cookies[$name] = array_merge($defaults, $options, ['value' => $value]);
        return $this;
    }

    /**
     * Set multiple cookies
     */
    public function setCookies(array $cookies): self
    {
        foreach ($cookies as $name => $cookie) {
            if (is_array($cookie)) {
                $value = $cookie['value'] ?? '';
                unset($cookie['value']);
                $this->setCookie($name, $value, $cookie);
            } else {
                $this->setCookie($name, $cookie);
            }
        }
        return $this;
    }

    /**
     * Get all cookies
     */
    public function getCookies(): array
    {
        return $this->cookies;
    }

    /**
     * Remove a cookie
     */
    public function removeCookie(string $name): self
    {
        // Set cookie with past expiration
        $this->setCookie($name, '', ['expires' => time() - 3600]);
        return $this;
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

        // Set cookies before headers
        foreach ($this->cookies as $name => $cookie) {
            setcookie(
                $name,
                $cookie['value'],
                [
                    'expires' => $cookie['expires'],
                    'path' => $cookie['path'],
                    'domain' => $cookie['domain'],
                    'secure' => $cookie['secure'],
                    'httponly' => $cookie['httponly'],
                    'samesite' => $cookie['samesite']
                ]
            );
        }

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
