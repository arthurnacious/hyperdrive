# Hyperdrive

A modern, high-performance PHP application framework inspired by NestJS
and designed as a reusable package. Hyperdrive installs into your
existing project and provides a modular architecture, Domain-Driven
Design structure, and extremely high performance through OpenSwoole or
Swoole.

## Vision

Hyperdrive brings NestJS-style modules and clean architectural patterns
to PHP. It is installed via Composer and used directly within your
`src/` directory. It does not scaffold a project like Laravel or Symfony
CLI.

## Performance First

### Multi-Runtime Architecture

  Runtime           Approx Requests/sec   Notes
  ----------------- --------------------- ----------------------------
  OpenSwoole        5,000--50,000         Coroutine runtime, fastest
  Swoole            4,000--40,000         Async runtime
  Traditional PHP   100--500              FPM, process-per-request

Hyperdrive works under all three.

## Module System (NestJS-Inspired)

``` php
#[Module(
    imports: [AuthModule::class],
    controllers: [UserController::class],
    injectables: [UserService::class, UserRepository::class],
    exports: [UserService::class]
)]
class UserModule {}
```

A module groups everything needed for a feature: controllers, services,
repositories, configuration, and providers.

## Domain-Driven Design Ready

Hyperdrive supports clear separation of architectural layers:

    src/
      Module/
        Users/
          Domain/
          Application/
          Infrastructure/
          UI/

## Modern PHP Features

-   PHP 8.4+
-   Attributes for routing, modules, services
-   Autowired dependency injection
-   PSR-compliant container, logging, and event patterns

## Concurrent Operations

``` php
$results = Concurrent::all([
    'user' => fn() => $userRepo->find($id),
    'posts' => fn() => $postRepo->findByUser($id),
    'notifications' => fn() => $notificationRepo->getUnread($id),
]);

$results['posts']; // results keep their keys, in the order given
```

`Concurrent::all()` runs every operation and returns all results under
their original keys, in input order, whichever finishes first. If one
throws, that exception is rethrown unchanged. Under OpenSwoole/Swoole the
operations run in coroutines and overlap; under Roadstar (or in tests)
they run one after another with the same result.

| Method | Returns |
| --- | --- |
| `all($operations)` | every result, keyed |
| `any($operations)` | the first operation that succeeds |
| `race($operations)` | whichever operation finishes first |
| `map($items, $fn)` | `$fn` applied to each item, order kept |

Under Roadstar, where each request owns a single database connection,
pin the sync driver so coroutines never share it:

``` php
Concurrent::setDriver(new SyncConcurrencyDriver());
```

## Server-Rendered Views

Routes return JSON by default. Add `#[Render]` and the returned
array/object is rendered as HTML by whichever view engine your
application binds - Hyperdrive stays template-agnostic, the same as
NestJS's `@Render()`.

``` php
#[Get('/courses/{slug}')]
#[Render('courses/show')]
public function show(string $slug): array
{
    return ['course' => $this->courses->findBySlug($slug)];
}
```

``` php
#[Module(injectables: [ViewEngineInterface::class => MustacheEngine::class])]
class AppModule {}
```

``` php
interface ViewEngineInterface
{
    public function render(string $view, array $data = []): string;
}
```

A `#[Render]` route that returns a `Response` (e.g. a redirect) passes it
through untouched. A `#[Render]` route with no engine bound fails with an
explicit error instead of silently answering in JSON.

## Installation

``` bash
composer require hyperdrive/framework
```

## Basic Application Example

``` php
$app = HyperdriveFactory::create(AppModule::class);
$app->listen(9501);
```

``` php
#[Module(controllers: [AppController::class])]
class AppModule {}
```

``` php
#[Route('/api')]
class AppController
{
    #[Get]
    public function index(): Response
    {
        return Response::json(['message' => 'Hello Hyperdrive']);
    }
}
```

Run:

    php server.php

## Production Features

### Health Check Endpoint

``` php
#[Get('/health')]
public function health(): Response
{
    return Response::json([
        'status' => 'healthy',
        'timestamp' => time(),
        'version' => '1.0.0',
    ]);
}
```

### Middleware Pipeline

``` php
#[Middleware(AuthMiddleware::class)]
class SecureController {}
```

Route middleware fails closed: if a middleware class is missing, isn't a
`MiddlewareInterface`, or throws while being constructed, the request
gets a 500 - the route never runs without the guard it declared.

## Testing

    composer test
    composer test-coverage

## Roadmap

-   CLI tool (`php please`)
-   Domain event system
-   Task scheduling
-   API versioning
-   Rate limiting middleware
-   Automatic OpenAPI documentation

## Contributing
#### Hyperdrive is open source and welcomes contributions! We're particularly interested in:

- Performance optimizations
- Middleware implementations
- Testing utilities
- Documentation improvements

## License

MIT License
