<?php

declare(strict_types=1);

namespace Hyperdrive\Drivers;

use Hyperdrive\Http\Request;
use Hyperdrive\Http\Response;

class RoadstarDriver extends AbstractServerDriver
{
    protected function startServer(int $port, string $host): void
    {
        $url = $this->getServerUrl($host, $port);
        $this->logServerStart('Roadstar', $url);

        // Roadstar doesn't actually start a server - it relies on Apache/Nginx
        // The actual request handling happens through handleRequest()
    }

    public function handleRequest(Request $request): Response
    {
        return $this->handleFrameworkRequest($request);
    }
}
