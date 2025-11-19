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
        if (!$this->router) {
            return new Response('Router not initialized', 500);
        }

        // Find the route
        $route = $this->router->findRoute(
            $request->getMethod(),
            $request->getPath()
        );

        if (!$route) {
            return new Response('Not Found', 404);
        }

        // Extract route parameters
        // $parameters = $route->extractParameters($request->getPath());
        $route->extractParameters($request->getPath());

        // TODO: Actually call the controller method with DI
        // For now, return a placeholder response
        return new Response(
            "Roadstar: Handled {$request->getMethod()} {$request->getPath()}",
            200,
            ['Content-Type' => 'text/plain']
        );
    }
}
