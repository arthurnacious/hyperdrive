<?php

declare(strict_types=1);

namespace Hyperdrive\Drivers;

use Hyperdrive\Http\Request;
use Hyperdrive\Http\Response;

class OpenSwooleDriver extends AbstractServerDriver
{
    public function boot(): void
    {
        $this->running = true;
    }

    protected function startServer(int $port, string $host): void
    {
        $url = $this->getServerUrl($host, $port);
        $this->logServerStart('OpenSwoole', $url);

        // Placeholder - will implement actual OpenSwoole server
        // Keep the process running for now
        while ($this->running) {
            sleep(1);
        }
    }

    public function handleRequest(Request $request): Response
    {
        return new Response('OpenSwoole: Request handled', 200);
    }
}
