<p align="center">
  <h1 align="center">Atom ⚛</h1>
  <p align="center">PHP 8.5 micro‑framework. Single PCRE router, template engine, DI, validation, sessions, database, CLI.</p>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/php-8.5-blue" alt="PHP 8.5">
  <img src="https://img.shields.io/badge/tests-passing-green" alt="tests passing">
  <img src="https://img.shields.io/badge/coverage-99%25-brightgreen" alt="99% coverage">
  <img src="https://img.shields.io/badge/license-GPL--3.0-orange" alt="GPL-3.0">
  <br>
  <img src="https://img.shields.io/badge/topic-router-red" alt="router">
  <img src="https://img.shields.io/badge/topic-pcre-purple" alt="pcre">
  <img src="https://img.shields.io/badge/topic-microframework-yellow" alt="microframework">
  <img src="https://img.shields.io/badge/topic-templates-teal" alt="templates">
  <img src="https://img.shields.io/badge/topic-validation-cyan" alt="validation">
</p>

---

## Why Atom

**Zero dependencies.** Just PHP 8.5. No ORM, no HTTP factory, no annotations - pure PHP with PCRE at its core.

**Minimal codebase.** Read the entire framework in 20 minutes. Every line tested — 99%+ coverage, zero warnings.

**One regex dispatches all routes.** 10 000 routes = one `preg_match` via `(?|...(*:N))` branch-reset + MARK. JIT-compiled, O(1) per request.

**Batteries included.** Validation on 18 attribute rules, Twig-like templates, sessions with CSRF rotation, CORS, file uploads, database, logger with rotation, CLI with help - things you'd otherwise compose from 5 packages.

**Built for APIs and full-stack.** JSON body parsing, Bearer token extraction, method spoofing, cache headers - everything you need for a REST API. Templates with inheritance, blocks, filters - everything you need for server-rendered pages.

**Use it for:** REST APIs, microservices, admin panels, static-site backends, MVPs, prototyping, educational projects, anything where you want full control without a framework fighting you.

---

## Scope of Application

| Scenario | Fit | Notes |
|---|---|---|
| **REST API** | Excellent | JSON body auto-parse, Bearer token, method spoofing, CORS, status codes |
| **Microservice** | Excellent | Zero deps, single-file deploy, 850 lines of code |
| **Admin panel** | Good | Templates with inheritance, CSRF, sessions, validation |
| **Static-site backend** | Good | Template engine, routing, config from .env |
| **MVP / Prototype** | Excellent | Full stack in one file, rapid iteration |
| **Hobby project** | Excellent | Easy to learn, zero config needed |
| **High-traffic API** | Good | O(1) routing, JIT-compiled PCRE, route cache |
| **Real-time / WebSocket** | Not suitable | No built-in event loop or persistent connections |
| **Enterprise CMS** | Not suitable | No ORM, no migrations, no admin generator |
| **E-commerce** | Possible | Would need custom cart/payment logic, DB only |
| **SPA backend** | Good | JSON API + CORS + JWT via Bearer token |
| **Server-rendered pages** | Good | Template inheritance, blocks, filters, auto-escape |

---

## Install

```bash
composer require globus-studio/atom
```

## Quick start

```php
use Atom\{Application, Config};

$app = new Application(Config::fromEnv(__DIR__ . '/.env'));

$app->router->get('/', 'HomeController@index');
$app->router->group('/api', ['Auth'], fn($r) => {
    $r->get('/users/{id}', 'UserController@show', 'user.show');
    $r->post('/users', 'UserController@create');
});

$app->run();
```

## Features

| Component | Description |
|---|---|
| **Router** | Single `preg_match` dispatch via `(?\|...(*:N))`. Groups, URL gen, attributes, cache. |
| **Templates** | Twig-like → compiled PHP classes. Extends, blocks, filters, raw blocks. |
| **Validation** | 18 attribute rules: `#[Required]` `#[Email]` `#[Regex]` `#[Min]` `#[Max]` `#[Integer]` `#[Between]` `#[In]` `#[Url]` `#[Nullable]` `#[Confirmed]` `#[Ip]` `#[Domain]` `#[Mac]` `#[FloatVal]` `#[Boolean]` `#[Uuid]` `#[Each]` |
| **Database** | Minimal PDO wrapper: `all()`, `one()`, `single()`, `run()`, prepared statements. |
| **Middleware** | Closure | object | string. Built-in: CORS, CSRF. |
| **Session** | `get/set/flash/regenerate`, CSRF token generation & rotation. |
| **CLI** | `bin/atom list/help/routes/cache`, custom commands with descriptions, `NO_COLOR` support. |
| **Request** | Property hooks, JSON body, Bearer token, `_method` spoofing, file uploads. |
| **Response** | `html/json/text/redirect/noContent`, cookies, cache headers, header injection shield. |
| **Container** | DI: `bind`, `singleton`, `instance`, `has`, recursive autowire. |
| **Config** | `fromEnv('.env')` with `APP_DEBUG`, `APP_CACHE_DIR`, `APP_VIEWS_DIR`, `APP_TIMEZONE`, `APP_LOG_LEVEL`, `APP_LOG_MAX_SIZE`, `APP_NAME`. |
| **Logger** | File-based, 7 levels, min-level filter, context, atomic writes, rotation, clear. |

## Example

```php
// .env
APP_DEBUG=true
DB_DSN=sqlite:/var/app/db.sqlite
APP_TIMEZONE=Europe/Moscow
```

```php
// public/index.php
use Atom\{Application, Config};
use Atom\Database\Database;
use Atom\Http\Response;
use Atom\Support\Logger;
use Atom\Validation\{Required, Email};

$config = Config::fromEnv(__DIR__ . '/../.env');
$app = new Application($config);

$app->container->singleton(Database::class,
    fn() => new Database($config->get('DB_DSN')));
$app->container->singleton(Logger::class,
    fn() => new Logger($config->get('APP_LOG_FILE'), $config->logLevel));

final class CreateUser {
    #[Required] public string $name = '';
    #[Required] #[Email] public string $email = '';
}

$app->router->post('/users', 'UserController@create');
$app->router->get('/users/{id}', 'UserController@show', 'user.show');

class UserController {
    public function __construct(private Database $db, private Logger $log) {}

    public function create(Request $req): Response {
        try {
            $dto = $req->validate(CreateUser::class);
        } catch (ValidationException $e) {
            return Response::json($e->errors, StatusCode::BAD_REQUEST);
        }
        $this->db->run('INSERT INTO users (name,email) VALUES (?,?)', [$dto->name, $dto->email]);
        $this->log->info('user created', ['name' => $dto->name]);
        return Response::json(['id' => $this->db->lastId()], StatusCode::CREATED);
    }

    public function show(string $id): Response {
        $user = $this->db->one('SELECT * FROM users WHERE id = ?', [$id]);
        return $user ? Response::json($user) : Response::json(['error' => 'Not found'], StatusCode::NOT_FOUND);
    }
}

$app->run();
```

## Project structure

```
src/Atom/
├── Config.php              # debug, cacheDir, viewsDir, timezone, logFile, logLevel, logMaxSize, appName, fromEnv(), get()
├── Application.php         # entry point, boot, run
├── Console/Console.php     # CLI: list, help, routes, cache, custom commands, NO_COLOR
├── Container/Container.php # DI: bind, singleton, instance, has, autowire
├── Database/Database.php   # PDO wrapper: all, one, single, run
├── Http/
│   ├── Request.php         # hooks, JSON body, Bearer, _method, file(), validate()
│   ├── Response.php        # html, json, text, redirect, cookies, cache, send()
│   ├── Session.php         # get/set, flash, regenerate, csrfToken, validateCsrf
│   ├── StatusCode.php      # enum 200..503
│   └── UploadedFile.php    # typed $_FILES: ok, size, ext, move()
├── Middleware/
│   ├── MiddlewareInterface.php
│   ├── Cors.php            # preflight + CORS headers, origin reflection
│   ├── Csrf.php            # CSRF token validation with rotation
│   └── Pipeline.php        # onion: Closure | object | string
├── Routing/
│   ├── Route.php           # #[Route] attribute
│   ├── CompiledRoute.php   # internal representation
│   ├── MatchedRoute.php    # match result
│   ├── RouteCompiler.php   # single PCRE regex
│   └── Router.php          # dispatch, groups, url(), cache, routes()
├── Support/
│   ├── Logger.php          # file logger: 7 levels, rotate, clear, maxSize
│   └── Regex.php           # PCRE wrapper
├── Validation/
│   └── Validator.php       # 18 attribute rules + ValidationException
└── View/
    ├── Compiler.php         # Twig-like → PHP, nested braces, for-loop shadow restore
    ├── Engine.php           # render, filters, globals
    └── Template.php         # base template class
```

## Running tests

```bash
composer test
composer test-coverage
```

## Docs & resources

- [Full documentation](docs/index.html) — sidebar, all components, code examples
- [Skill set](.opencode/skills/atom/) — AI‑assistant knowledge: routing, HTTP, templates, validation, DI, CLI, middleware
- [License](LICENSE) — GPL-3.0-or-later

## License

GPL-3.0-or-later. See [LICENSE](LICENSE).
