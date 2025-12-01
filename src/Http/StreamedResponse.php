<?php
// src/Http/StreamedResponse.php

declare(strict_types=1);

namespace Hyperdrive\Http;

class StreamedResponse extends Response
{
    public function __construct(
        private $resource,
        private string $filename,
        private string $contentType = 'application/octet-stream'
    ) {
        parent::__construct('', 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => sprintf('attachment; filename="%s"', $filename),
        ]);
    }

    public function send(): void
    {
        http_response_code($this->getStatusCode());

        foreach ($this->getHeaders() as $name => $value) {
            header("$name: $value");
        }

        if (is_resource($this->resource)) {
            fpassthru($this->resource);
            fclose($this->resource);
        }
    }
}
