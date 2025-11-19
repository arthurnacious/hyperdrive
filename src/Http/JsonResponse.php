<?php

declare(strict_types=1);

namespace Hyperdrive\Http;

class JsonResponse extends Response
{
    public function __construct(array $data, int $status = 200, array $headers = [])
    {
        $headers['Content-Type'] = 'application/json';

        parent::__construct(
            json_encode($data, JSON_THROW_ON_ERROR),
            $status,
            $headers
        );
    }
}
