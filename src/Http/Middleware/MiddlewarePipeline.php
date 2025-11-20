<?php

declare(strict_types=1);

namespace Hyperdrive\Http\Middleware;

use Hyperdrive\Http\Request;
use Hyperdrive\Http\Response;

class MiddlewarePipeline implements RequestHandlerInterface
{
    private array $middleware = [];
    private int $index = 0;
    private RequestHandlerInterface $finalHandler;

    public function __construct(RequestHandlerInterface $finalHandler)
    {
        $this->finalHandler = $finalHandler;
    }

    public function pipe(MiddlewareInterface $middleware): void
    {
        $this->middleware[] = $middleware;
    }

    public function handle(Request $request): Response
    {
        if ($this->index >= count($this->middleware)) {
            return $this->finalHandler->handle($request);
        }

        $middleware = $this->middleware[$this->index];
        $this->index++;

        return $middleware->handle($request, $this);
    }

    public function reset(): void
    {
        $this->index = 0;
    }
}
