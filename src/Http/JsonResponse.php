<?php

declare(strict_types=1);

namespace Hyperdrive\Http;

class JsonResponse extends Response
{
    public function __construct(mixed $data, int $status = 200, array $headers = [])
    {
        $headers['Content-Type'] = 'application/json';

        $content = '';
        if ($status !== 204 && $status !== 304) {
            $content = $data !== null ? json_encode($data, JSON_THROW_ON_ERROR) : 'null';
        }

        parent::__construct($content, $status, $headers);
    }

    public static function json(mixed $data, int $status = 200, array $headers = []): self
    {
        $headers['Content-Type'] = 'application/json';

        return new self($data, $status, $headers);
    }
}
