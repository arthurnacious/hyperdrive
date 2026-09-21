# Getting Started with Hyperdrive

Hyperdrive is a NestJS-inspired PHP framework distributed as a Composer
**library** rather than a project skeleton. There is no `hyperdrive new
my-app` command that scaffolds a project for you — you create an ordinary
PHP project, `composer require` the framework, and build your application
in your own `src/` directory using Hyperdrive's attributes and base
classes. Hyperdrive supplies the module system, dependency injection
container, router, HTTP layer, DTO validation, middleware pipeline,
WebSocket support and a concurrency helper; everything else about your
project's layout is up to you.

This guide walks through everything from an empty directory to a running
"Hello World" endpoint, and then goes deep on every major subsystem. Every
code example in the "Your First Application" section below was written
into a real scratch project and actually run against `php -S` during the
writing of this guide — it is not aspirational.

---

## Table of Contents

1. [Requirements](#requirements)
2. [Installation](#installation)
3. [Project Structure](#project-structure)
4. [Your First Application](#your-first-application)
5. [How `boot()` Works](#how-boot-works)
6. [Routing](#routing)
7. [Modules](#modules)
8. [Dependency Injection](#dependency-injection)
9. [Middleware](#middleware)
10. [Request and Response](#request-and-response)
11. [DTOs and Validation](#dtos-and-validation)
12. [File Uploads](#file-uploads)
13. [The `Controller` Helper Base Class](#the-controller-helper-base-class)
14. [Configuration](#configuration)
15. [Runtime Drivers: Roadstar vs. OpenSwoole vs. Swoole](#runtime-drivers-roadstar-vs-openswoole-vs-swoole)
16. [Concurrency](#concurrency)
17. [WebSockets](#websockets)
18. [JWT Security Helper](#jwt-security-helper)
19. [Testing Your Application](#testing-your-application)
20. [Known Limitations](#known-limitations)

---

## Requirements

- **PHP 8.4 or newer** (Hyperdrive's `composer.json` declares `"php": ">=8.4"`
  and there is no upper bound — PHP 8.5 works too).
- **Composer**.
- Optionally, the **OpenSwoole** or **Swoole** PECL extension if you want
  Hyperdrive's high-performance coroutine server instead of the
  traditional PHP-FPM/Apache/Nginx model. Neither is required to get
  started — without either extension installed, Hyperdrive automatically
  falls back to its traditional "Roadstar" driver, which is what this
  guide uses throughout (it's what a "front controller" setup means).

> If you do install both `openswoole` and `swoole` as PHP extensions, only
> load one of them at a time in `php.ini`/`conf.d` — they both register
> the same global compatibility functions (e.g. `swoole_coroutine_create`)
> and PHP will refuse to load the second one, emitting warnings on every
> invocation. Pick whichever one your deployment target uses.

---

## Installation

Create a new, empty project directory and initialize Composer:

```bash
mkdir my-app && cd my-app
composer init --name=acme/my-app --type=project --no-interaction
```

Then require the framework:

```bash
composer require hyperdrive/framework
```

> Hyperdrive is not (yet) published on Packagist. Until it is, you install
> it either from a VCS repository (pointing Composer at the framework's
> git URL) or, for local development against a checkout on disk, via a
> Composer **path repository**:
>
> ```json
> {
>     "require": {
>         "hyperdrive/framework": "@dev"
>     },
>     "repositories": [
>         {
>             "type": "path",
>             "url": "/absolute/path/to/hyperdrive"
>         }
>     ],
>     "minimum-stability": "dev",
>     "prefer-stable": true
> }
> ```
>
> A path repository symlinks the framework into your project's `vendor/`
> directory, so any change you make to the framework source is picked up
> immediately without reinstalling — this is exactly how this guide's
> examples were built and verified.

Set up your own project's autoloading in `composer.json` (Hyperdrive does
not scaffold this for you):

```json
{
    "autoload": {
        "psr-4": {
            "App\\": "src/"
        }
    }
}
```

Run `composer install` (or `composer dump-autoload` if you edited
`composer.json` by hand after already installing).

---

## Project Structure

Hyperdrive imposes almost no structural convention on your project — the
only fixed rule is that your project's Composer autoload root (however you
map it, e.g. `App\` → `src/`) contains your modules, controllers, DTOs and
services. A convention Hyperdrive is *designed to support*, following the
Domain-Driven Design layering mentioned in the README, looks like this for
a larger app:

```
my-app/
├── composer.json
├── config/                      # your project's config overrides (optional)
│   └── server.php
├── public/
│   └── index.php               # the front controller (traditional PHP mode)
├── src/
│   ├── AppModule.php            # your root module
│   └── Module/
│       └── Users/
│           ├── Domain/
│           ├── Application/
│           ├── Infrastructure/
│           └── UI/
│               └── UserController.php
└── vendor/
```

For a small app, or the example in this guide, a single flat `src/`
directory with a module and a controller is entirely sufficient — nothing
requires the DDD layering.

---

## Your First Application

This section builds a minimal, fully working Hyperdrive app served through
a traditional PHP **front controller** — the pattern used with PHP-FPM
behind Apache/Nginx, or PHP's own built-in development server. Every
snippet below is exactly what was written and tested; the terminal output
shown is real.

### 1. The controller

```php
<?php
// src/AppController.php

declare(strict_types=1);

namespace App;

use Hyperdrive\Attributes\Http\Route;
use Hyperdrive\Attributes\Http\Verbs\Get;
use Hyperdrive\Http\Response;

#[Route('/')]
class AppController
{
    #[Get]
    public function index(): Response
    {
        return Response::json(['message' => 'Hello Hyperdrive']);
    }
}
```

- `#[Route('/')]` on the class sets the controller's path **prefix**. It's
  optional — a controller with no `#[Route]` attribute simply has an
  empty prefix.
- `#[Get]` on a public method registers it as a route. Every public,
  non-constructor method that carries an HTTP verb attribute
  (`#[Get]`, `#[Post]`, `#[Put]`, `#[Patch]`, `#[Delete]`, `#[Options]`)
  becomes a route; methods without one of these attributes are simply
  ignored by the router (they can still be regular helper methods).
- `Response::json(array $data, int $status = 200, array $headers = [])`
  is a static convenience constructor on `Hyperdrive\Http\Response` that
  JSON-encodes the payload and sets `Content-Type: application/json`.

### 2. The module

```php
<?php
// src/AppModule.php

declare(strict_types=1);

namespace App;

use Hyperdrive\Application\Module;

#[Module(controllers: [AppController::class])]
class AppModule
{
}
```

Every Hyperdrive application needs at least one module — the **root
module** — passed to `Hyperdrive::create()`. A module is just an empty
class carrying a `#[Module(...)]` attribute; Hyperdrive never instantiates
it, it only reads its attribute via reflection at boot time.

### 3. The front controller

```php
<?php
// public/index.php

declare(strict_types=1);

require __DIR__ . '/../vendor/autoload.php';

use App\AppModule;
use Hyperdrive\Application\Hyperdrive;
use Hyperdrive\Http\Request;

$app = Hyperdrive::create(
    rootModule: AppModule::class,
    driver: 'roadstar',
    environment: 'production',
);

$app->boot();

$request = Request::createFromGlobals();
$response = $app->getDriver()->handleRequest($request);
$response->send();
```

This is the entire front controller. Walking through it:

- `Hyperdrive::create(rootModule, driver, environment, url)` constructs
  the application (it does **not** boot it yet). `driver: 'roadstar'`
  explicitly selects the traditional PHP driver — the one designed to be
  invoked once per request from a front controller, with no persistent
  server process of its own. (You can also pass `driver: 'auto'`, the
  default, which picks `openswoole` → `swoole` → `roadstar` based on
  which PHP extensions are loaded; on a plain PHP-FPM/Apache box with
  neither extension installed, `'auto'` already resolves to `'roadstar'`.)
- `$app->boot()` loads configuration, walks the module tree, registers
  every controller's routes on the router, and prepares the driver (see
  [How boot() Works](#how-boot-works) below for the full sequence).
- `Request::createFromGlobals()` builds Hyperdrive's own immutable
  `Request` object from PHP's superglobals (`$_GET`, `$_POST`, `$_COOKIE`,
  `$_FILES`, `$_SERVER`, and the raw request body).
- `$app->getDriver()->handleRequest($request)` is the actual per-request
  entry point for the traditional driver. Note that this is **not**
  `$app->listen()` — `listen()` is for the driver's own event loop
  (meaningful for OpenSwoole/Swoole; for Roadstar it just logs a message,
  since Apache/Nginx/FPM already owns the request/response loop). A front
  controller calls `handleRequest()` directly, once, per HTTP request.
- `$response->send()` emits the HTTP status line, headers, cookies and
  body using PHP's normal `header()`/`echo` functions.

### 4. Point a web server at it

For local development, PHP's built-in server can serve the front
controller directly — passing it as the **router script** means every
request (regardless of path) is routed through `index.php`, exactly like
an Apache/Nginx rewrite rule would do in production:

```bash
php -S localhost:8000 public/index.php
```

For production, point Apache's `DocumentRoot` (or Nginx's `root` +
`try_files ... /index.php`) at `public/`, with a rewrite rule that sends
every non-file request to `public/index.php`. Hyperdrive does not care
which web server fronts it — `Request::createFromGlobals()` only depends
on standard PHP superglobals that any SAPI populates.

### 5. Run it

```bash
$ curl -i http://localhost:8000/
HTTP/1.1 200 OK
Content-Type: application/json

{"message":"Hello Hyperdrive"}
```

That's the whole loop: install → module → controller → front controller →
running server.

### Adding a route with a path parameter

```php
#[Get('/hello/{name}')]
public function hello(string $name): Response
{
    return Response::json(['message' => "Hello, {$name}!"]);
}
```

```bash
$ curl -s http://localhost:8000/hello/World
{"message":"Hello, World!"}
```

Because the class also carries `#[Route('/')]`, the effective path is the
class prefix plus the method's own path, joined with a single `/`
(`Hyperdrive\Support\PathBuilder` handles this joining and normalizes
leading/trailing slashes, so you never get `//` or a missing `/`).
Route parameters (`{name}`) are extracted from the URL and automatically
type-converted to `int`, `float` or `bool` if the controller method
parameter declares one of those types; otherwise they're passed as
strings.

---

## How `boot()` Works

Understanding the boot sequence makes everything else in this guide
easier to reason about. `Hyperdrive::create(...)` only constructs three
shared, framework-lifetime objects — a `Container`, a `Router`, and a
`ModuleRegistry` — and resolves which driver to use. Nothing else happens
until you call `$app->boot()`, which does the following, in order:

1. **Environment flag.** `Environment::setTesting($this->environment ===
   'testing')` is called (only takes effect when the environment string is
   literally `'testing'`).
2. **Configuration loading**, in three layered passes, each one
   deep-merging over the previous (see [Configuration](#configuration)):
   1. The framework's own defaults, from `config/` inside the Hyperdrive
      package itself (currently just `server.php`).
   2. Your project's overrides, from `<project root>/config/`.
   3. Environment-specific overrides, from
      `<project root>/config/<environment>/` (e.g. `config/production/`).
3. `Config::set('app.url', $url)` — the `url` argument you passed to
   `Hyperdrive::create()` (default `http://localhost:3000`) is stored in
   config under `app.url`.
4. **Module registration.** `ModuleRegistry::register($rootModule)` walks
   the module tree recursively (see [Modules](#modules)): it registers
   every imported module's controllers and bindings *before* the current
   module's own, applying prefixes hierarchically as it goes.
5. The router's fast static-route lookup map is built
   (`Router::buildRouteMap()`).
6. The chosen driver is handed the shared `Container` and `Router`, and
   its own `boot()` runs — for the server drivers (Roadstar, OpenSwoole,
   Swoole) this initializes a `ControllerDispatcher` and pre-resolves any
   configured global middleware instances.
7. **Environment-aware error reporting.** If `environment === 'production'`,
   PHP error display is turned off entirely (`error_reporting(0)`). For
   any other environment string, full error reporting is turned on *and*
   a development banner is printed directly to stdout:
   ```
   🚀 Hyperdrive Development Mode
   ⚠️  Errors will be logged to console
   ```
   Because this is a plain `echo`, it becomes part of your first response
   body if you're serving over HTTP with anything other than
   `environment: 'production'` — worth knowing before you're confused by
   an emoji banner prepended to your JSON in a browser. Use
   `environment: 'production'` for anything other than local CLI
   experimentation with error visibility.

After `boot()` returns, the application is fully wired: every controller
in the module tree is registered on the router, every module's
`injectables` are bound into the container, and the driver is ready to
either `listen()` (OpenSwoole/Swoole) or have `handleRequest()` called
per-request (Roadstar).

---

## Routing

Routes are declared entirely with PHP 8 attributes — there is no separate
routes file.

### HTTP verb attributes

| Attribute | Target | Constructor |
|---|---|---|
| `#[Get(path: '')]` | method | `path` |
| `#[Post(path: '')]` | method | `path` |
| `#[Put(path: '')]` | method | `path` |
| `#[Patch(path: '')]` | method | `path` |
| `#[Delete(path: '')]` | method | `path` |
| `#[Options(path: '')]` | method | `path` |

All of them take a single optional `path` string (default `''`, meaning
"the controller's own prefix, with nothing appended"). A single method
may only usefully carry one verb attribute; the router scans every
attribute on the method and the **last matching verb attribute it finds
wins** (in practice, only put one on a given method).

### The `#[Route]` attribute (class-level prefix)

```php
#[Route('/users')]
class UserController
{
    #[Get]                  // GET  /users
    public function index(): Response { /* ... */ }

    #[Get('/{id}')]          // GET  /users/{id}
    public function show(int $id): Response { /* ... */ }

    #[Post]                 // POST /users
    public function store(CreateUserDto $dto): Response { /* ... */ }
}
```

`#[Route]`'s `prefix` is joined with the enclosing module's own prefix
(see [Modules](#modules)) — so the *effective* base path of a controller
is `<module prefix>/<controller prefix>`, and the effective path of a
route is `<module prefix>/<controller prefix>/<method path>`.

### Path parameters and type coercion

`{name}` segments in a path (`/users/{id}/posts/{postId}`) are captured
by name and passed as controller method arguments with a matching
parameter name. If the method parameter is typed `int`, `float`, or
`bool`, the raw string segment is converted; any other scalar type (or no
type) is passed through as a string. Order doesn't matter — parameters
are matched to the method by **name**, not position, so you can declare
`Request $request` before, after, or between route parameters, or omit
capturing a segment entirely if your method doesn't need it.

### How parameters resolve, in order

For each controller method parameter, `ControllerDispatcher` resolves a
value by trying, in this order:

1. A route path parameter with a matching name.
2. `Request $request` (or any subclass) — the current request object.
3. A `Dto`-subclass type — see [DTOs and Validation](#dtos-and-validation)
   for how the request body is validated and hydrated into it.
4. Otherwise, resolution falls through to the dependency injection
   container — see [Dependency Injection](#dependency-injection).

### Performance: the static route map

At the end of `boot()` (and again whenever you register more controllers
at runtime), the router builds an O(1) `"METHOD:path" → route` lookup map
for every route that has **no** `{parameter}` segments. Requests to those
static paths skip pattern matching entirely. Routes with parameters fall
back to a linear scan through `RouteDefinition::matches()`, which compiles
each route's path into a regular expression on the fly.

### `OPTIONS` / CORS preflight

You never need to manually register an `OPTIONS` handler for a path that
already has other methods registered. The router tracks, for every
registered path, which HTTP methods exist on it; an incoming `OPTIONS`
request to that path is answered directly (in O(1)) with a `204` and an
`Allow` header listing the other registered methods for that path — no
controller code runs. You can still declare an explicit `#[Options]`
method if you want custom preflight behavior for a specific path, but you
don't have to.

---

## Modules

A module is declared with `#[Module(...)]` on an otherwise-empty class:

```php
#[Module(
    imports: [OtherModule::class],
    controllers: [UserController::class],
    injectables: [UserService::class, SomeInterface::class => SomeImpl::class],
    exports: [UserService::class],
    gateways: [ChatGateway::class],
    static: [],
    prefix: 'api/v1',
)]
class UserModule {}
```

| Property | Type | Meaning |
|---|---|---|
| `imports` | `class-string[]` | Other module classes this module depends on. Imported modules are registered *before* this module's own controllers/bindings, with this module's prefix prepended to whatever prefix they declare. |
| `controllers` | `class-string[]` | Controller classes to register with the router under this module's effective prefix. |
| `injectables` | mixed array | Services to make available through the container. See below. |
| `exports` | `class-string[]` | Which of this module's `injectables` (by class or interface name) other modules are allowed to depend on. The container itself stays a single flat/global instance — bindings are always technically reachable — but `Hyperdrive::boot()` runs a boundary check (see [Module Boundary Validation](#module-boundary-validation)) that throws if a controller or injectable in one module depends on another module's non-exported (or non-imported) service. |
| `gateways` | `class-string[]` | WebSocket gateway classes for this module. Automatically registered into the shared `WebSocketRegistry` when the module is registered — see [WebSockets](#websockets). |
| `static` | `array` | Arbitrary metadata stored and retrievable via `ModuleRegistry::getStatic()`; not otherwise interpreted by the framework. |
| `prefix` | `string` | This module's own path prefix, combined with its parent's accumulated prefix. |

### The `injectables` array and interface binding

`injectables` is intentionally a mixed array, and `ModuleRegistry`
processes it in two passes:

```php
#[Module(injectables: [
    PaymentGatewayInterface::class => StripePaymentGateway::class, // string key → interface binding
    UserRepository::class,                                        // plain value → concrete class
])]
```

1. **First pass:** every entry with a **string key** that is itself an
   existing interface (`interface_exists($key)`) is registered as an
   interface→implementation binding on the container
   (`Container::bind($key, $value)`). Any controller or service that
   type-hints `PaymentGatewayInterface` in its constructor will receive a
   `StripePaymentGateway` instance.
2. **Second pass:** every entry that is *not* a string-keyed interface
   binding (i.e. every plain concrete class listed as a value) is eagerly
   resolved through the container once, which triggers Hyperdrive's
   autowiring (see [Dependency Injection](#dependency-injection)) and
   caches the instance. Errors while eagerly resolving one of these are
   silently swallowed (so a broken `injectables` entry doesn't crash the
   whole app at boot).

### Prefix compounding across nested imports

Prefixes compound **top-down**: the *importing* module's accumulated
prefix is passed to the modules it imports, and combined with each
imported module's own `prefix`. Given:

```php
#[Module(controllers: [UserController::class], prefix: 'users')]
class UsersModule {}

#[Module(imports: [UsersModule::class], prefix: 'api')]
class AppModule {}
```

booting with `AppModule` as the root module, `UsersModule`'s controllers
(and therefore `UserController`'s routes) end up mounted under
`/api/users` — the root module's own `prefix: 'api'` is passed down as
the parent prefix when `UsersModule` is registered, and combined with
`UsersModule`'s own `prefix: 'users'`. Nesting further imports compounds
further (`PathBuilder::build()` joins each level with a single `/`, so
you never get double slashes or a missing
leading slash regardless of how deeply nested the chain is).

### A module is only registered once

`ModuleRegistry::register()` checks `has($moduleClass)` first and returns
immediately if that module class was already registered — so it's safe
for the same module to be imported by two different parent modules
without its controllers being registered (and its routes duplicated)
twice.

### Module Boundary Validation

The DI container itself is a single, flat, shared instance for the whole
application (see [Dependency Injection](#dependency-injection)) — nothing
about the container's *resolution* mechanism is module-scoped. What *is*
module-scoped is a **boot-time check**: after the module tree is
registered, `Hyperdrive::boot()` calls
`ModuleRegistry::validateModuleBoundaries()`, which reflects the
constructor of every registered controller and every module's own
concrete `injectables`, and for each class/interface-typed dependency it
finds, checks whether that dependency is reachable:

- If nothing registered the dependency as an injectable anywhere in the
  module tree (e.g. it's a framework class like `Request`, or an
  unrelated vendor class), the check ignores it — boundaries only apply
  to classes some module actually declared ownership of.
- If the dependency is owned by the *same* module that's asking for it,
  it's always allowed.
- If it's owned by a *different* module, that owning module must list the
  dependency (by class **or** interface name — whichever the consumer
  actually type-hints) in its own `exports`, **and** the consuming module
  must import the owning module, directly or transitively through its
  own `imports`.

Violating either rule throws a `Hyperdrive\Exceptions\ModuleBoundaryException`
with a message telling you exactly which class, which module, and which
of the two rules (missing `exports` entry vs. missing `imports` entry)
was broken:

```php
#[Module(injectables: [BillingService::class])] // no `exports`!
class BillingModule {}

#[Module(imports: [BillingModule::class], controllers: [InvoiceController::class])]
class InvoiceModule {}

// InvoiceController's constructor takes a BillingService — boot() throws:
// "Hyperdrive\...\InvoiceController (registered in Hyperdrive\...\InvoiceModule)
//  depends on Hyperdrive\...\BillingService, which belongs to
//  Hyperdrive\...\BillingModule but is not in its exports. Add
//  Hyperdrive\...\BillingService to Hyperdrive\...\BillingModule's
//  #[Module(exports: [...])], or drop the dependency."
```

Adding `exports: [BillingService::class]` to `BillingModule` (or dropping
the constructor dependency) fixes it. This check runs once per `boot()`
call, purely via reflection over already-registered metadata — it adds no
runtime cost to request handling and does not change how a dependency is
actually resolved once boot succeeds.

---

## Dependency Injection

`Hyperdrive\Container\Container` is a minimal, reflection-based
**autowiring** container — there's no configuration file describing
services; the container inspects constructors at resolution time.

```php
class UserService
{
    public function __construct(
        private UserRepository $repository,
        private Logger $logger,
    ) {}
}
```

Calling `$container->get(UserService::class)`:

1. Checks whether an interface→implementation binding exists for
   `UserService::class` (from `Container::bind()`, typically set up via a
   module's `injectables`); if so, resolves the bound concrete class
   instead.
2. Otherwise, reflects the class's constructor. For every constructor
   parameter, if it's untyped or has a builtin type (`string`, `int`,
   `array`, ...), a `ContainerException` is thrown immediately — the
   container can only autowire typed, non-builtin (class/interface)
   dependencies. (This is why route parameters and request/DTO data are
   resolved separately by the HTTP layer *before* the container ever sees
   a controller's constructor — controllers should only have
   class-typed dependencies in their constructors.)
3. Every resolvable parameter is recursively resolved the same way — so
   `UserService`'s dependency on `UserRepository` will, in turn, resolve
   `UserRepository`'s own constructor dependencies, and so on.
4. The fully constructed instance is cached by class/interface name. The
   **same** instance is returned on every subsequent `get()` call for
   that name for the lifetime of the container — every service resolved
   through the container is effectively a singleton for as long as the
   `Container` instance lives (which, for OpenSwoole/Swoole, is the
   lifetime of the worker process, not a single request — see
   [Known Limitations](#known-limitations)).
5. Circular dependencies (A needs B needs A) are detected and raise a
   `ContainerException` rather than recursing forever.

You can also explicitly register bindings yourself, outside of a module's
`injectables`, by calling `$app->getContainer()->bind($abstract,
$concrete)` or `->singleton($abstract, $concrete)` after `boot()` (or
before, if you construct the container yourself for advanced use cases).

Controllers themselves are resolved through this same container — see
"controller instances are pooled" under [Runtime Drivers](#runtime-drivers-roadstar-vs-openswoole-vs-swoole).

---

## Middleware

A middleware is any class implementing `MiddlewareInterface`:

```php
use Hyperdrive\Http\Middleware\MiddlewareInterface;
use Hyperdrive\Http\Middleware\RequestHandlerInterface;
use Hyperdrive\Http\Request;
use Hyperdrive\Http\Response;

class RequestLoggerMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, RequestHandlerInterface $handler): Response
    {
        // ...do something before...
        $response = $handler->handle($request);
        // ...do something after...
        return $response;
    }
}
```

Middleware is onion-style: call `$handler->handle($request)` to continue
to the next middleware (or the controller, if you're the last one in the
chain) and get its `Response` back; you can inspect/modify the request
before calling it and the response after.

There are three ways to attach middleware, and they execute in this
order for every request:

### 1. Global middleware (every request, every route)

Configure it via `config/middleware.php` in your project:

```php
<?php
// config/middleware.php
return [
    'global' => [
        \App\RequestLoggerMiddleware::class,
    ],
];
```

Global middleware instances are resolved through the container **once,
during `boot()`** (not per-request) and reused for every request, so a
global middleware class itself behaves like a singleton service for the
life of the driver.

### 2. Controller-level middleware

```php
#[Middleware([AuthMiddleware::class])]
class AccountController
{
    // every route on this controller runs AuthMiddleware
}
```

### 3. Method-level middleware

```php
#[Middleware([RateLimitMiddleware::class])]
#[Post('/transfer')]
public function transfer(): Response { /* ... */ }
```

Controller-level and method-level middleware are collected together
(class-level first, then method-level), de-duplicated while preserving
order, and resolved through the container fresh for each request that
route handles (unlike global middleware, which is pre-resolved once at
boot). If a middleware class fails to resolve (e.g. a bad dependency),
the error is logged and that specific middleware is simply skipped rather
than failing the whole request.

**Execution order for a single request:** global middleware (in the order
declared in config) → controller/method middleware (controller-level
first, method-level second, de-duplicated) → the controller method
itself.

---

## Request and Response

### `Request`

`Hyperdrive\Http\Request` is **immutable** — every "with"-style method
returns a new instance rather than mutating the current one.

```php
$request->getMethod();          // 'GET', 'POST', ...
$request->getPath();            // '/users/42' (query string stripped)
$request->getContentType();     // e.g. 'application/json', or null
$request->getBody();            // array — parsed JSON body if Content-Type
                                 // is application/json, otherwise the raw
                                 // form-encoded POST fields
$request->all();                // query params + body + attributes, merged
$request->input('user.name');   // dot-notation lookup across all()
$request->query('page', 1);     // dot-notation lookup, query string only
$request->json('meta.tags');    // dot-notation lookup, JSON body only
$request->only(['id', 'name']);
$request->except(['password']);
$request->has('email');

// Files
$request->file('avatar');       // ?UploadedFile[]
$request->firstFile('avatar');  // ?UploadedFile
$request->hasFile('avatar');
$request->allFiles();

// Immutable helpers
$withAttr = $request->withAttribute('user_id', 42); // new instance
$request->getAttribute('user_id');                   // read one back

// "Injected" data (a second, independent bag, often set by middleware to
// hand pre-resolved data — e.g. an authenticated user — down the chain)
$withInjected = $request->withInjected('user', $userEntity);
$request->injected('user');
$request->allInjected();

// Magic access: property-style reads fall through to input() then
// injected() — $request->name is equivalent to
// $request->input('name') ?? $request->injected('name')
$request->name;
isset($request->name);
```

`Request::createFromGlobals()` builds one from PHP's superglobals
(traditional/Roadstar mode); `Request::createFromSwoole($swooleRequest)`
builds one from an OpenSwoole request object (used internally by
`OpenSwooleDriver`).

### `Response`

```php
new Response(string|resource $content = '', int $status = 200, array $headers = [], array $cookies = []);

Response::json(array $data, int $status = 200, array $headers = []);
Response::file(string $fileContent, string $filename, string $contentType = 'application/octet-stream');
Response::pdf(string $pdfContent, string $filename = 'document.pdf');
Response::stream($resource, string $filename, string $contentType = 'application/octet-stream'); // returns a StreamedResponse

$response
    ->setCookie('token', $value, ['expires' => time() + 3600, 'httponly' => true])
    ->removeCookie('old_cookie');

$response->getContent();     // string
$response->getStatusCode();
$response->getHeaders();
$response->send();           // emit via header()/echo — traditional SAPI only
```

`Hyperdrive\Http\JsonResponse extends Response` and is what
`Response::json()` constructs under the hood; it sets
`Content-Type: application/json` and JSON-encodes the payload for you
(an explicit `null` `$status` of `204`/`304` produces an empty body per
HTTP semantics, matching how those status codes are supposed to behave).

**Returning things other than a `Response` from a controller.** You don't
have to construct a `Response` yourself — if a controller method returns
a plain string, it's wrapped in `new Response((string) $result)`; if it
returns an array or object, it's wrapped in a `JsonResponse`. Returning a
`Response` (or `JsonResponse`, or `StreamedResponse`) directly is used
as-is. This applies uniformly across all three drivers.

---

## DTOs and Validation

A DTO (`Hyperdrive\Http\Dto` subclass) both **hydrates and validates** the
request body in one step, when you type-hint it as a controller method
parameter — you never call anything explicitly; the dispatcher does it
for you and, if validation fails, a `422 Unprocessable Entity` JSON
response is returned automatically (before your controller method ever
runs).

```php
use Hyperdrive\Http\Dto;
use Hyperdrive\Http\Dto\Validation\IsEmail;
use Hyperdrive\Http\Dto\Validation\IsString;
use Hyperdrive\Http\Dto\Validation\MinLength;
use Hyperdrive\Http\Dto\Validation\NotEmpty;

class CreateUserDto extends Dto
{
    #[IsString]
    #[NotEmpty]
    #[MinLength(2)]
    public string $name;

    #[IsEmail]
    public string $email;
}
```

```php
#[Post('/users')]
public function store(CreateUserDto $dto): Response
{
    // Only reached if validation passed. $dto->name / $dto->email are
    // already type-converted and populated from the request body.
    return $this->created(['name' => $dto->name, 'email' => $dto->email]);
}
```

A failing request:

```bash
$ curl -s -X POST /users -H 'Content-Type: application/json' -d '{"name":"A","email":"not-an-email"}'
{"error":"Validation failed","errors":{"name":["Must be at least 2 characters"],"email":["Must be a valid email address"]}}
```

with a `422` status code, on every driver.

### Built-in validation attributes

All target `Attribute::TARGET_PROPERTY` and implement `ValidatorInterface`
(`validate(mixed $value, string $field): bool` + `getMessage(): string`):

| Attribute | Constructor args | Checks |
|---|---|---|
| `#[IsString]` | — | value is a `string` |
| `#[IsInt]` | — | value is an `int` (or numeric) |
| `#[IsEmail]` | — | value passes `filter_var(..., FILTER_VALIDATE_EMAIL)` |
| `#[IsArray(type: ?string, valueIn: ?array)]` | optional element-type name and/or an allow-list of values | value is an array, optionally every element matches `gettype()` === `$type` and/or is in `$valueIn` |
| `#[NotEmpty]` | — | value is not empty |
| `#[MinLength(int $min)]` | required | string length ≥ `$min` |
| `#[MinValue(int $min)]` | required | numeric value ≥ `$min` |

### The validation pipeline, in order

`Dto::__construct(array $data, array $context = [])` runs, per property:

1. **Type check** against the property's declared *builtin* PHP type
   (`int`, `float`, `bool`, `string`, `array`) — if the raw incoming value
   doesn't match (and isn't `null` for a nullable property), an error is
   recorded immediately and, if any type errors exist, a
   `ValidationException` is thrown right here (attribute-based validators
   never even run on badly-typed data).
2. **Hydration** — each scalar-typed property is cast to its declared
   type and assigned. Non-builtin-typed properties (objects, other DTOs)
   are left untouched by this step.
3. **Attribute-based validation** — every `#[...]` validator attribute on
   every property runs; failures accumulate (they are *not*
   fail-fast — you get every error for every field in one response).
4. If any attribute validators failed, a `ValidationException` (→ 422) is
   thrown.

Only after all of the above succeeds does the DTO reach your controller.

### Custom validation with `#[ValidateWith]`

For validation logic that doesn't fit a reusable attribute — cross-field
checks, business rules, or anything needing injected services — mark a
**private method** on the DTO:

```php
class CreateUserDto extends Dto
{
    #[IsString]
    #[ValidateWith('validateUsername')]
    public string $username;

    private function validateUsername(string $value): void
    {
        if ($value === 'admin') {
            $this->addError('username', 'Username "admin" is reserved');
        }
    }
}
```

The method receives the field's own value as its first parameter (an
optional second `string $field` parameter, if declared, receives the
field name — useful if the same method backs multiple properties). Call
`$this->addError($field, $message)` (or `addErrorIf()` /
`addErrorUnless()`, or pass multiple messages/an array to `addError()`)
to record a failure; the method's return value is ignored.

`DtoFactory` (which the dispatcher uses to build every DTO) inspects each
non-builtin-typed parameter of a **`validate(...)` method**, if your DTO
defines one, and resolves each one through the DI container, passing them
as extra constructor arguments — this is how a `#[ValidateWith]` method
can call out to an injected service (e.g. to check a username against the
database):

```php
class CreateUserDto extends Dto
{
    #[ValidateWith('validateUsername')]
    public string $username;

    public function validate(UserRepository $users): void
    {
        // DtoFactory sees this method, resolves UserRepository from the
        // container, and passes it into the constructor for you.
    }

    private function validateUsername(string $value): void
    {
        // ...
    }
}
```

### Cross-field validation

A validator attribute implementing `CrossFieldValidatorInterface` (rather
than the plain `ValidatorInterface`) receives **every** property's value,
not just its own field's, via `validateWithContext(mixed $value, array
$allValues, string $propertyName): bool` — useful for things like
"password confirmation must match password".

### Context

Extra data can be threaded into a DTO's custom validation methods via the
constructor's `$context` array (accessible from inside the DTO with
`$this->getContext($key, $default)`) — for example, `Request` itself is
commonly passed this way so a `#[ValidateWith]` method can inspect the
client's IP or headers.

### Other `Dto` methods

```php
$dto->isValid();      // bool
$dto->hasErrors();    // bool, or hasErrors('field') for one field
$dto->getErrors();    // array<string, string[]>
$dto->toArray();      // every *public* property, as an array
```

---

## File Uploads

```php
#[Post('/avatar')]
public function upload(Request $request): Response
{
    $file = $request->firstFile('avatar'); // ?UploadedFile

    if (!$file || !$file->isValid()) {
        return $this->badRequest('No valid file uploaded');
    }

    $path = $file->move(__DIR__ . '/../storage/uploads');

    return $this->ok(['path' => $path]);
}
```

`UploadedFile` wraps PHP's `$_FILES` entry format and exposes
`getClientOriginalName()`, `getClientOriginalExtension()`,
`getClientMimeType()`, `getSize()`, `getError()`, `isValid()`,
`getPathname()`, `getContent()` and `getResource()` (a readable stream
handle, for piping directly to external storage without buffering the
whole file into memory), plus `move($directory, ?$name = null)`, which
delegates to `Hyperdrive\Http\FileUploader` — it uses
`move_uploaded_file()` for genuine uploads and falls back to `rename()`
for files that aren't real HTTP uploads (useful when constructing
`UploadedFile` manually in tests). Multi-file inputs (`<input
name="photos[]" multiple>`) are automatically normalized into a list of
`UploadedFile` objects instead of PHP's native parallel-array `$_FILES`
shape — `$request->file('photos')` returns `UploadedFile[]` either way.

---

## The `Controller` Helper Base Class

Controllers don't have to extend anything — a plain class works fine, as
shown throughout this guide. `Hyperdrive\Http\Controller` is an optional
abstract base class with protected convenience methods for common JSON
responses, verified to work correctly with the DI-resolved controller
dispatch:

```php
use Hyperdrive\Http\Controller;

class AccountController extends Controller
{
    #[Get]
    public function show(): Response
    {
        return $this->ok(['id' => 1]);              // 200
    }

    #[Post]
    public function store(): Response
    {
        return $this->created(['id' => 2]);          // 201
    }
}
```

Available methods: `ok()`, `created()`, `accepted()`, `noContent()`
(204), `badRequest()` (400), `unauthorized()` (401), `forbidden()` (403),
`notFound()` (404), `conflict()` (409), `unprocessableEntity()` (422),
`serverError()` (500), the generic `error(string $message, int $status)`
and `json(mixed $data, int $status)`, plus `withCookie()` /
`jsonWithCookie()` / `removeCookie()` for responses that also need to set
or clear an HTTP-only cookie (defaults: `secure`, `httponly`,
`samesite=Strict`, 1 hour expiry).

---

## Configuration

`Hyperdrive\Config\Config` is a singleton, keyed by **dot notation**:

```php
Config::get('server.http.port', 3000);
Config::set('app.url', 'https://example.com');
Config::has('auth.jwt.secret');
```

Every `*.php` file inside a config directory returns a plain array and is
keyed under **its own filename** — `config/server.php` populates the
`server.*` namespace, `config/middleware.php` populates `middleware.*`,
and so on; there's no need to register filenames anywhere.

Directories are merged, not replaced, in this order (later wins, and
merging is recursive for nested arrays rather than a full overwrite):

1. The framework's own `config/` (ships with `server.php`: HTTP
   host/port/`max_request` and WebSocket host/port/enabled, all readable
   from `HYPERDRIVE_HOST`, `HYPERDRIVE_PORT`, `HYPERDRIVE_MAX_REQUEST`,
   `HYPERDRIVE_WEBSOCKET_*` environment variables).
2. Your project's `<project root>/config/`.
3. Your project's `<project root>/config/<environment>/` — the
   `environment` string you passed to `Hyperdrive::create()`, e.g.
   `config/production/server.php` only loads when you booted with
   `environment: 'production'`.

A separate, unrelated class, `Hyperdrive\Config\Environment`, reads the
`APP_ENV` environment variable directly (not through `Config`) and offers
`Environment::get()`, `::is('staging')`, `::isProduction()`,
`::isDevelopment()`, `::isTesting()`, `::isLocal()` (true for
`local`/`development`/`testing`). It's a general-purpose utility your own
code can use; the framework itself only touches it to flag test mode
during `boot()`.

---

## Runtime Drivers: Roadstar vs. OpenSwoole vs. Swoole

| | Roadstar | OpenSwoole | Swoole |
|---|---|---|---|
| Model | Traditional PHP, one process per request (Apache/Nginx/FPM) | Long-running coroutine server, one process handles many concurrent requests | Same coroutine model as OpenSwoole |
| Entry point | A front controller calling `handleRequest()` once per request | `$app->listen($port, $host)` starts an embedded HTTP(+WebSocket) server | Same as OpenSwoole |
| Typical throughput (per README) | 100–500 req/s | 5,000–50,000 req/s | 4,000–40,000 req/s |
| Requires a PHP extension | No | `ext-openswoole` | `ext-swoole` |

`Hyperdrive::create(driver: 'auto')` (the default) picks
`openswoole` → `swoole` → `roadstar`, in that order, based on which
extension is loaded — so the exact same application code runs unmodified
under all three; only your entry-point script differs:

```php
// Front controller (Roadstar) — see "Your First Application" above.
$app->boot();
$response = $app->getDriver()->handleRequest(Request::createFromGlobals());
$response->send();
```

```php
// server.php (OpenSwoole / Swoole)
$app = Hyperdrive::create(AppModule::class, driver: 'auto', environment: 'production');
$app->boot();
$app->listen(port: 9501, host: '0.0.0.0'); // blocks, running the event loop
```

```bash
php server.php
```

### Controller instances are pooled

All three drivers reuse the **same controller instance** across every
request that hits it — `ControllerDispatcher` resolves each controller
class through the container once and keeps it in an internal pool,
because controllers are expected to be stateless. This is true even for
Roadstar, where a fresh PHP process (and thus a fresh `Container`) is
created for every HTTP request by the SAPI anyway — the pooling mostly
matters for OpenSwoole/Swoole, where the `Container` (and therefore the
controller pool) lives for the entire worker process's lifetime across
thousands of requests. **Do not give a controller (or any service
resolved through the container) mutable instance state that accumulates
across requests** — under OpenSwoole/Swoole that state will leak for the
life of the worker.

### CORS and error handling under OpenSwoole/Swoole

Both coroutine drivers answer `OPTIONS` preflight requests before routing
even runs, and catch `ValidationException` specifically to return a
proper `422` (all three drivers now share this behavior consistently).
Outside of `environment: 'production'`, uncaught exceptions are logged
(including the full stack trace) and returned in the `500` response body;
in production, only a generic "Internal error" message is returned.

---

## Concurrency

`Hyperdrive\Concurrency\Concurrent` is a small abstraction over
concurrent operations, useful when you have several independent, slow
operations (e.g. multiple downstream API/database calls) to run together
rather than sequentially:

```php
use Hyperdrive\Concurrency\Concurrent;

$results = Concurrent::map(
    ['users', 'posts', 'comments'],
    fn (string $resource) => $httpClient->get("/{$resource}"),
);

$first = Concurrent::race([
    fn () => $primary->fetch(),
    fn () => $fallback->fetch(),
]);

$any = Concurrent::any([
    fn () => $a->fetch(),
    fn () => $b->fetch(),
]); // resolves with the first success, ignores failures unless all fail
```

It picks its actual implementation automatically the first time you call
it: a synchronous, sequential fallback when running under PHPUnit/Pest or
when neither `openswoole` nor `swoole` is loaded, and a true
coroutine-based concurrent driver when one of those extensions is
available and you're not in a test process. This makes `Concurrent::` the
same API to write against regardless of which driver is actually serving
the request — it degrades gracefully (just sequential, not actually
concurrent) rather than erroring under Roadstar or in tests. You can also
force a specific driver with `Concurrent::setDriver(...)` if you need to.

---

## WebSockets

Hyperdrive's WebSocket support follows the same attribute-driven,
class-based style as HTTP:

```php
use Hyperdrive\Attributes\WebSocket\WebSocketGateway;
use Hyperdrive\Attributes\WebSocket\OnConnection;
use Hyperdrive\Attributes\WebSocket\OnMessage;
use Hyperdrive\Attributes\WebSocket\OnDisconnection;
use Hyperdrive\WebSocket\WebSocketConnection;
use Hyperdrive\WebSocket\WebSocketMessage;

#[WebSocketGateway(path: '/chat', prefix: 'ws')]
class ChatGateway
{
    #[OnConnection]
    public function onConnect(WebSocketConnection $connection): void
    {
        $connection->send(['type' => 'welcome']);
    }

    #[OnMessage(type: 'chat.message')]
    public function onChatMessage(WebSocketMessage $message): void
    {
        $message->getConnection()->send(['echo' => $message->getData()]);
    }

    #[OnDisconnection]
    public function onDisconnect(WebSocketConnection $connection): void
    {
        // clean up
    }
}
```

- `#[WebSocketGateway(path, prefix)]` on the class defines which URL path
  the gateway handles (`prefix` + `path`, joined the same way HTTP routes
  are).
- `#[OnConnection]` / `#[OnDisconnection]` mark (at most one) method each
  to run on connect/disconnect.
- `#[OnMessage(type: ?string)]` can be declared on multiple methods to
  route incoming messages by a `type` field in the decoded JSON payload;
  a method with `type: null` matches any message type not claimed by a
  more specific handler.
- `WebSocketConnection` gives you `send(array $data)`, `close()`,
  `getId()`, and per-connection `get/setAttribute()` for storing
  arbitrary state (e.g. an authenticated user) against a specific
  connection.

Registering the gateway is automatic — list it in a module's
`#[Module(gateways: [ChatGateway::class])]` array (like `ChatGateway`
above) and `Hyperdrive::boot()` registers it into the shared
`WebSocketRegistry` while walking the module tree, exactly the way
`controllers` gets registered into the router. A gateway's effective path
compounds with its module's prefix the same way a controller's does (see
[Prefix compounding across nested imports](#prefix-compounding-across-nested-imports)).

`OpenSwooleDriver::boot()` receives that already-populated
`WebSocketRegistry` (falling back to creating an empty one if the driver
is booted standalone, e.g. in a test, without going through
`Hyperdrive::boot()`) and creates a `WebSocketGatewayDispatcher`; when it
starts its server, it registers `handshake`/`message`/`close` handlers
that look up the matching gateway by path and dispatch to the right
method.

---

## JWT Security Helper

`Hyperdrive\Security\JwtService` issues and verifies HMAC-SHA256-signed
JSON Web Tokens:

```php
use Hyperdrive\Security\JwtService;

$jwt = new JwtService();

$token = $jwt->encode(['sub' => $user->id, 'role' => 'admin']);

try {
    $payload = $jwt->verify($token);
} catch (\InvalidArgumentException $e) {
    // token isn't in header.payload.signature form
} catch (\RuntimeException $e) {
    // "Invalid JWT signature" or "JWT token expired"
}
```

`encode()` always adds `iat` (issued-at), `exp` (expiry) and `iss`
(issuer) claims automatically; any claim you pass explicitly **overrides**
the corresponding auto-generated one (so you can set your own `exp` if
you need a different lifetime for one particular token). Configure it via:

| Config key | Default | Env var it reads (framework default `config/server.php` doesn't set these — add your own `config/auth.php`) |
|---|---|---|
| `auth.jwt.secret` | `'hyperdrive-default-secret-change-in-production'` | — |
| `auth.jwt.algorithm` | `'HS256'` (header only — signing is always HMAC-SHA256 regardless of this value) | — |
| `auth.jwt.expiry` | `3600` seconds | — |

**Change the default secret in production** — `JwtService` will happily
sign and verify tokens with the built-in placeholder secret if you never
configure `auth.jwt.secret`, which is not something you want deployed.

---

## Testing Your Application

Hyperdrive's own test suite uses [Pest](https://pestphp.com/) (with a mix
of Pest's own `it()`/`test()` style and plain PHPUnit `TestCase` classes
in the same suite — both work side by side under the same `pest` binary).
The same setup works for testing your own application:

```bash
composer require --dev pestphp/pest pestphp/pest-plugin-arch
./vendor/bin/pest --init
```

Because Hyperdrive's container, router and modules are plain PHP objects
you construct yourself, you don't need a running HTTP server to test a
controller — you can build the pieces directly:

```php
use Hyperdrive\Container\Container;
use Hyperdrive\Routing\Router;
use Hyperdrive\Http\Request;
use Hyperdrive\Http\ControllerDispatcher;

test('index returns hello world', function () {
    $router = new Router();
    $router->registerController(AppController::class);

    $route = $router->findRoute('GET', '/');
    $dispatcher = new ControllerDispatcher(new Container());

    $response = $dispatcher->dispatch($route, new Request(
        server: ['REQUEST_METHOD' => 'GET', 'REQUEST_URI' => '/'],
    ));

    expect($response->getStatusCode())->toBe(200);
});
```

Give your project its own `phpunit.xml` (pointing `<testsuite>` at your
`tests/` directory and `<source><include>` at your `src/`) — without one,
Pest generates a temporary one on every run, which works, but is slower
and (in Pest 5.2.1 specifically) has been observed to misbuild its
generated CLI arguments in some environments; shipping your own
`phpunit.xml` avoids that code path entirely.

---

## Known Limitations

Documented plainly, rather than glossed over, since this guide is meant
to be trustworthy. Two items that used to be listed here — `#[Module(gateways:
[...])]` not being auto-registered, and `exports` not being enforced —
have since been fixed (see [WebSockets](#websockets) and
[Module Boundary Validation](#module-boundary-validation) respectively).
What's left:

- **`swoole` and `openswoole` cannot both be loaded as PHP extensions at
  the same time** — they register the same global compatibility
  functions and PHP will silently refuse to load whichever one comes
  second (alphabetically, in `conf.d`), only emitting a warning. Pick one
  per deployment.
- **Everything resolved through the container behaves like a
  process-lifetime singleton.** This is fine (even desirable) under
  Roadstar, where the process itself only lives for one request anyway,
  but under OpenSwoole/Swoole it means any service with mutable instance
  state will retain that state across every request the worker ever
  handles, for the life of the worker. Design services and controllers to
  be stateless, or explicitly scope any needed per-request state through
  the `Request` object's `attributes`/`injected` bags instead of instance
  properties. `exports` boundary validation (above) governs which module
  a dependency may come from, not how long the resolved instance lives —
  that's still governed entirely by the container's own singleton-per-name
  caching.
- **`#[Module(exports: [...])]` boundary validation only covers
  constructor dependencies of registered controllers and modules' own
  concrete `injectables`.** It doesn't (and can't, without much deeper
  static analysis) trace dependencies resolved dynamically inside a
  method body (e.g. `$container->get(SomeClass::class)` called directly),
  a DTO's `validate(...)` method parameters, or middleware classes not
  also listed in some module's `injectables`. Those are still resolved
  from the same flat, shared container and will succeed at runtime
  regardless of module boundaries — the check is a boot-time lint for the
  common, declarative case, not a runtime access-control layer.
