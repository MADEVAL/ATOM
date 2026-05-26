<p align="center">
  <h1 align="center">Atom ⚛</h1>
  <p align="center">PHP 8.5 micro‑framework. Single PCRE router, template engine, DI, validation, sessions, database, CLI.</p>
</p>

<p align="center">
  <img src="https://img.shields.io/badge/php-8.5-blue" alt="PHP 8.5">
  <img src="https://img.shields.io/badge/tests-347-green" alt="347 tests">
  <img src="https://img.shields.io/badge/coverage-99%25-brightgreen" alt="99% coverage">
  <img src="https://img.shields.io/badge/license-GPL--3.0-orange" alt="GPL-3.0">
  <img src="https://img.shields.io/badge/size-571%20LOC-lightgrey" alt="571 LOC">
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

**571 lines.** Read the entire framework in 20 minutes. Every line tested to death - 347 tests, 99%+ coverage.

**One regex dispatches all routes.** 10 000 routes = one `preg_match` via `(?|...(*:N))` branch-reset + MARK. JIT-compiled, O(1) per request.

**Batteries included.** Validation on PCRE-attributes, Twig-like templates, sessions with CSRF, CORS, file uploads, database, logger, CLI - things you'd otherwise compose from 5 packages.

**Built for APIs and full-stack.** JSON body parsing, Bearer token extraction, method spoofing, cache headers - everything you need for a REST API. Templates with inheritance, blocks, filters - everything you need for server-rendered pages.

**Use it for:** REST APIs, microservices, admin panels, static-site backends, MVPs, prototyping, educational projects, anything where you want full control without a framework fighting you.

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
| **Validation** | PCRE-attribute: `#[Required]` `#[Email]` `#[Regex]` `#[Min]` `#[Max]` |
| **Database** | Minimal PDO wrapper: `all()`, `one()`, `single()`, `run()`, prepared statements. |
| **Middleware** | Closure | object | string. Built-in: CORS, CSRF. |
| **Session** | `get/set/flash/regenerate`, CSRF token generation & validation. |
| **CLI** | `bin/atom routes`, `bin/atom cache`, custom commands. |
| **Request** | Property hooks, JSON body, Bearer token, `_method` spoofing, file uploads. |
| **Response** | `html/json/text/redirect/noContent`, cookies, cache headers, header injection shield. |
| **Container** | DI: `bind`, `singleton`, `instance`, recursive autowire. |
| **.env** | PCRE parser via `Config::fromEnv('.env')`. |
| **Logger** | File-based, 4 levels, min-level filter, context, atomic writes. |

## Example

```php
// .env
APP_DEBUG=true
DB_DSN=sqlite:/var/app/db.sqlite
```

```php
// public/index.php
use Atom\{Application, Config, Console\Console};
use Atom\Database\Database;
use Atom\Http\Response;
use Atom\Support\Logger;
use Atom\Validation\{Required, Email};

$config = Config::fromEnv(__DIR__ . '/../.env');
$app = new Application($config);

$app->container->singleton(Database::class,
    fn() => new Database($config->get('DB_DSN')));
$app->container->singleton(Logger::class,
    fn() => new Logger(__DIR__ . '/../storage/app.log', $config->debug ? Logger::DEBUG : Logger::WARN));

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
├── Config.php              # debug, cacheDir, viewsDir, fromEnv(), get()
├── Application.php         # entry point, boot, run
├── Console/Console.php     # CLI: routes, cache, custom commands
├── Container/Container.php # DI: bind, singleton, instance, autowire
├── Database/Database.php   # PDO wrapper: all, one, single, run
├── Http/
│   ├── Request.php         # hooks, JSON body, Bearer, _method, file()
│   ├── Response.php        # html, json, text, redirect, cookies, cache
│   ├── Session.php         # get/set, flash, regenerate, csrfToken
│   ├── StatusCode.php      # enum 200..500
│   └── UploadedFile.php    # typed $_FILES: ok, size, ext, move()
├── Middleware/
│   ├── Cors.php            # preflight + CORS headers
│   ├── Csrf.php            # CSRF token validation
│   └── Pipeline.php        # onion: Closure | object | string
├── Routing/
│   ├── Route.php           # #[Route] attribute
│   ├── RouteCompiler.php   # compiles routes to single PCRE regex
│   └── Router.php          # dispatch, groups, url(), cache
├── Support/
│   ├── Logger.php          # file logger: debug, info, warn, error
│   └── Regex.php           # PCRE wrapper
├── Validation/
│   └── Validator.php       # attribute rules: Required, Email, Regex, Min, Max
└── View/
    ├── Compiler.php         # Twig-like → PHP
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
