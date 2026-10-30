<?php

declare(strict_types=1);

namespace Hyperdrive\Tests\View;

use Hyperdrive\Attributes\Http\Render;
use Hyperdrive\Attributes\Http\Route;
use Hyperdrive\Attributes\Http\Verbs\Get;
use Hyperdrive\Container\Container;
use Hyperdrive\Drivers\RoadstarDriver;
use Hyperdrive\Http\Request;
use Hyperdrive\Http\Response;
use Hyperdrive\Routing\Router;
use Hyperdrive\View\ViewEngineInterface;
use PHPUnit\Framework\TestCase;

class RenderTestEngine implements ViewEngineInterface
{
    public function render(string $view, array $data = []): string
    {
        return "<{$view}>" . json_encode($data);
    }
}

class RenderTestOtherEngine implements ViewEngineInterface
{
    public function render(string $view, array $data = []): string
    {
        return "other:{$view}";
    }
}

class RenderTestCourse
{
    public string $title = 'Guitar';
    private string $secret = 'hidden';
}

#[Route('/render')]
class RenderTestController
{
    #[Get('/array')]
    #[Render('courses/show')]
    public function array(): array
    {
        return ['title' => 'Guitar'];
    }

    #[Get('/object')]
    #[Render('courses/show')]
    public function object(): RenderTestCourse
    {
        return new RenderTestCourse();
    }

    #[Get('/nothing')]
    #[Render('home')]
    public function nothing(): void {}

    #[Get('/redirect')]
    #[Render('courses/show')]
    public function redirect(): Response
    {
        return new Response('', 302, ['Location' => '/login']);
    }

    #[Get('/json')]
    public function json(): array
    {
        return ['title' => 'Guitar'];
    }
}

class RenderTest extends TestCase
{
    private function handle(string $path, ?string $engine = RenderTestEngine::class): Response
    {
        $container = new Container();
        if ($engine !== null) {
            $container->bind(ViewEngineInterface::class, $engine);
        }

        $router = new Router();
        $router->registerController(RenderTestController::class);

        $driver = new RoadstarDriver(RenderTestController::class, 'testing');
        $driver->setContainer($container);
        $driver->setRouter($router);
        $driver->boot();

        return $driver->handleRequest(new Request(server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => $path]));
    }

    public function test_router_records_the_view_name(): void
    {
        $router = new Router();
        $router->registerController(RenderTestController::class);

        $this->assertSame('courses/show', $router->findRoute('GET', '/render/array')->getView());
        $this->assertNull($router->findRoute('GET', '/render/json')->getView());
    }

    public function test_render_route_returns_html_from_the_bound_engine(): void
    {
        $response = $this->handle('/render/array');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('text/html; charset=UTF-8', $response->getHeaders()['Content-Type']);
        $this->assertSame('<courses/show>{"title":"Guitar"}', $response->getContent());
    }

    public function test_object_results_pass_only_public_properties(): void
    {
        $this->assertSame('<courses/show>{"title":"Guitar"}', $this->handle('/render/object')->getContent());
    }

    public function test_void_render_route_renders_with_no_data(): void
    {
        $this->assertSame('<home>[]', $this->handle('/render/nothing')->getContent());
    }

    public function test_returned_response_passes_through_untouched(): void
    {
        $response = $this->handle('/render/redirect');

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('/login', $response->getHeaders()['Location']);
    }

    public function test_routes_without_render_still_return_json(): void
    {
        $response = $this->handle('/render/json');

        $this->assertSame('application/json', $response->getHeaders()['Content-Type']);
        $this->assertSame('{"title":"Guitar"}', $response->getContent());
    }

    public function test_rebinding_the_interface_swaps_engines(): void
    {
        $this->assertSame('other:courses/show', $this->handle('/render/array', RenderTestOtherEngine::class)->getContent());
    }

    public function test_missing_engine_binding_fails_with_a_clear_error(): void
    {
        $response = $this->handle('/render/array', null);

        $this->assertSame(500, $response->getStatusCode());
        $this->assertStringContainsString('no ViewEngineInterface is bound', $response->getContent());
    }
}
