<?php

declare(strict_types=1);

namespace Hyperdrive\Drivers;

use Hyperdrive\Http\Request;
use Hyperdrive\Http\Response;

class RoadstarDriver extends AbstractServerDriver
{
    public function boot(): void
    {
        $this->running = true;
    }

    protected function startServer(int $port, string $host): void
    {
        $url = $this->getServerUrl($host, $port);
        $this->logServerStart('Roadstar', $url);

        // Roadstar doesn't actually start a server - it relies on Apache/Nginx
        // So we don't need to keep the process running
    }

    public function handleRequest(Request $request): Response
    {
        // For Roadstar, request handling is done through the traditional PHP lifecycle
        return new Response('Roadstar: Request handled', 200);
    }
}
