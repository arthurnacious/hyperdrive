<?php

declare(strict_types=1);

namespace Hyperdrive\Drivers;

use Hyperdrive\Http\Request;
use Hyperdrive\Http\Response;

class SwooleDriver extends AbstractServerDriver
{

    protected function startServer(int $port, string $host): void
    {
        $url = $this->getServerUrl($host, $port);
        $this->logServerStart('Swoole', $url);

        // Placeholder - will implement actual Swoole server
        // Keep the process running for now
        while ($this->running) {
            sleep(1);
        }
    }

    public function handleRequest(Request $request): Response
    {
        return new Response('Swoole: Request handled', 200);
    }
}
