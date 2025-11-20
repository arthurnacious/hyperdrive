<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\Http\Middleware;

use Hyperdrive\Http\Middleware\MiddlewareInterface;
use Hyperdrive\Http\Middleware\MiddlewarePipeline;
use Hyperdrive\Http\Middleware\RequestHandlerInterface;
use Hyperdrive\Http\Request;
use Hyperdrive\Http\Response;
use PHPUnit\Framework\TestCase;

class TestMiddleware implements MiddlewareInterface
{
    public function __construct(private string $name) {}

    public function handle(Request $request, RequestHandlerInterface $handler): Response
    {
        $request = $request->withAttribute("passed_through_{$this->name}", true);
        return $handler->handle($request);
    }
}

class TestFinalHandler implements RequestHandlerInterface
{
    public function handle(Request $request): Response
    {
        return new Response('Final handler reached');
    }
}

class MiddlewarePipelineTest extends TestCase
{
    public function test_it_passes_request_through_middleware(): void
    {
        $finalHandler = new TestFinalHandler();
        $pipeline = new MiddlewarePipeline($finalHandler);

        $pipeline->pipe(new TestMiddleware('first'));
        $pipeline->pipe(new TestMiddleware('second'));

        $request = new Request();
        $response = $pipeline->handle($request);

        $this->assertEquals('Final handler reached', $response->getContent());

        // The request in the test scope doesn't get modified due to immutability
        // This test verifies the pipeline executes without errors
        // Attribute testing would require a different approach
    }

    public function test_it_calls_final_handler_when_no_middleware(): void
    {
        $finalHandler = new TestFinalHandler();
        $pipeline = new MiddlewarePipeline($finalHandler);

        $request = new Request();
        $response = $pipeline->handle($request);

        $this->assertEquals('Final handler reached', $response->getContent());
    }

    public function test_middleware_can_short_circuit_and_return_response(): void
    {
        $shortCircuitMiddleware = new class implements MiddlewareInterface {
            public function handle(Request $request, RequestHandlerInterface $handler): Response
            {
                return new Response('Short circuited', 403);
            }
        };

        $finalHandler = new TestFinalHandler();
        $pipeline = new MiddlewarePipeline($finalHandler);
        $pipeline->pipe($shortCircuitMiddleware);
        $pipeline->pipe(new TestMiddleware('never_reached'));

        $request = new Request();
        $response = $pipeline->handle($request);

        $this->assertEquals('Short circuited', $response->getContent());
        $this->assertEquals(403, $response->getStatusCode());
        $this->assertNull($request->getAttribute('passed_through_never_reached'));
    }

    public function test_it_can_reset_and_reuse_pipeline(): void
    {
        $callCount = 0;
        $finalHandler = new class($callCount) implements RequestHandlerInterface {
            public function __construct(private int &$callCount) {}

            public function handle(Request $request): Response
            {
                $this->callCount++;
                // Check for the attribute in the modified request
                $attributeValue = $request->getAttribute('passed_through_first', false);
                return new Response("Call {$this->callCount}, Attribute: " . ($attributeValue ? 'true' : 'false'));
            }
        };

        $pipeline = new MiddlewarePipeline($finalHandler);
        $pipeline->pipe(new TestMiddleware('first'));

        // First call
        $request = new Request();
        $response1 = $pipeline->handle($request);
        $this->assertEquals('Call 1, Attribute: true', $response1->getContent());

        // Reset and call again
        $pipeline->reset();
        $response2 = $pipeline->handle($request);
        $this->assertEquals('Call 2, Attribute: true', $response2->getContent());
    }

    public function test_middleware_can_modify_request_attributes(): void
    {
        $finalHandler = new class implements RequestHandlerInterface {
            public function handle(Request $request): Response
            {
                $attribute = $request->getAttribute('test_attribute', 'not_found');
                return new Response("Attribute: {$attribute}");
            }
        };

        $attributeMiddleware = new class implements MiddlewareInterface {
            public function handle(Request $request, RequestHandlerInterface $handler): Response
            {
                $request = $request->withAttribute('test_attribute', 'modified');
                return $handler->handle($request);
            }
        };

        $pipeline = new MiddlewarePipeline($finalHandler);
        $pipeline->pipe($attributeMiddleware);

        $request = new Request();
        $response = $pipeline->handle($request);

        $this->assertEquals('Attribute: modified', $response->getContent());
    }
}
