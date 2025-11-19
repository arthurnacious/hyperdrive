<?php

declare(strict_types=1);

namespace Hyperdrive\Drivers;

use Hyperdrive\Http\Request;
use Hyperdrive\Http\Response;
use OpenSwoole\Http\Server as OpenSwooleServer;

class OpenSwooleDriver extends AbstractServerDriver
{
    private ?OpenSwooleServer $server = null;

    protected function startServer(int $port, string $host): void
    {
        $this->server = new OpenSwooleServer($host, $port);

        $this->server->on('start', function (OpenSwooleServer $server) use ($host, $port) {
            $url = $this->getServerUrl($host, $port);
            $this->logServerStart('OpenSwoole', $url);
        });

        $this->server->on('request', function (
            \OpenSwoole\Http\Request $swooleRequest,
            \OpenSwoole\Http\Response $swooleResponse
        ) {
            $this->handleSwooleRequest($swooleRequest, $swooleResponse);
        });

        $this->server->start();
    }

    public function handleRequest(Request $request): Response
    {
        // This method is for internal framework use
        // Actual HTTP requests are handled via handleSwooleRequest
        return new Response('OpenSwoole: Request handled internally', 200);
    }

    private function handleSwooleRequest(
        \OpenSwoole\Http\Request $swooleRequest,
        \OpenSwoole\Http\Response $swooleResponse
    ): void {
        // Convert Swoole request to framework request
        $request = $this->createRequestFromSwoole($swooleRequest);

        // Handle the request using our framework (inherited from AbstractServerDriver)
        $response = $this->handleFrameworkRequest($request);

        // Convert framework response to Swoole response
        $this->sendSwooleResponse($response, $swooleResponse);
    }

    private function createRequestFromSwoole(\OpenSwoole\Http\Request $swooleRequest): Request
    {
        return new Request(
            query: $swooleRequest->get ?? [],
            request: $swooleRequest->post ?? [],
            attributes: [],
            cookies: $swooleRequest->cookie ?? [],
            files: $swooleRequest->files ?? [],
            server: $swooleRequest->server ?? [],
            content: $swooleRequest->rawContent() ?: null
        );
    }

    private function sendSwooleResponse(Response $response, \OpenSwoole\Http\Response $swooleResponse): void
    {
        $swooleResponse->status($response->getStatusCode());

        foreach ($response->getHeaders() as $name => $value) {
            $swooleResponse->header($name, $value);
        }

        $swooleResponse->end($response->getContent());
    }
}
