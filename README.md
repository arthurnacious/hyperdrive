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
```

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

## Testing

    composer test
    composer test-coverage

## Roadmap

-   CLI tool (`php please`)
-   Database layer with repositories
-   Domain event system
-   Task scheduling
-   API versioning
-   Rate limiting middleware
-   Automatic OpenAPI documentation

## Contributing
#### Hyperdrive is open source and welcomes contributions! We're particularly interested in:

- Performance optimizations
- Additional database drivers
- Middleware implementations
- Testing utilities
- Documentation improvements

## License

MIT License
