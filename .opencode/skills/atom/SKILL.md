# Atom Framework

PHP 8.5 micro-framework. Single-regex router, PCRE template engine, DI, validation, sessions, database, logger, CLI, .env.
571 lines, 29 classes, 99%+ coverage (347 tests).

> Topic files: `routing.md`, `http.md`, `templates.md`, `validation.md`, `middleware.md`, `di.md`, `database.md`, `cli.md`, `logger.md`

## Project structure

```
src/Atom/
├── Config.php              # debug, cacheDir, viewsDir
├── Application.php         # entry point, boot, run
├── Console/Console.php     # CLI: routes, cache, custom commands
├── Container/Container.php # DI: bind, singleton, instance, autowire
├── Database/Database.php   # PDO wrapper: all, one, single, run
├── Http/
│   ├── Request.php         # hooks, JSON body, Bearer token, _method
│   ├── Response.php        # html, json, redirect, cookies, text, noContent, cache
│   ├── Session.php         # get/set, flash, regenerate, csrfToken
│   ├── StatusCode.php      # enum 200..500
│   └── UploadedFile.php    # typed $_FILES: ok, size, ext, move()
├── Middleware/
│   ├── Cors.php            # preflight + CORS headers
│   ├── Csrf.php            # CSRF token validation
│   └── Pipeline.php        # onion: Closure | object | string
├── Routing/
│   ├── Route.php           # #[Route] attribute
│   ├── CompiledRoute.php   # internal representation
│   ├── MatchedRoute.php    # match result
│   ├── RouteCompiler.php   # single PCRE regex
│   └── Router.php          # dispatch, groups, url(), cache
├── Support/Regex.php       # PCRE wrapper
├── Validation/Validator.php# attribute rules + exception
└── View/
    ├── Compiler.php         # Twig-like → PHP
    ├── Engine.php           # render, filters, globals
    └── Template.php         # base class
```

## Quick start

```php
$app = new Application(new Config(
    debug: true,
    viewsDir: __DIR__ . '/../views',
    cacheDir: __DIR__ . '/../storage/cache',
));

$app->router->get('/', 'HomeController@index');
$app->router->group('/api', ['auth'], fn($r) => {
    $r->get('/users/{id}', 'UserController@show', 'user.show');
    $r->post('/users', 'UserController@create');
});

$app->run();
```

## Config

```php
$config = new Config(
    debug: false,        // true = show stack traces on errors
    cacheDir: '/tmp/atom',
    viewsDir: __DIR__ . '/templates',
);
$app = new Application($config);
```

## Performance

- Router: **one** `preg_match` per request via `(?|...(*:N))` branch-reset + MARK
- Routes cache: PHP `var_export` include (no unserialize)
- Templates: compile to PHP classes, disk cache, OPCache-friendly
- Property hooks: zero-overhead computed properties

## Regex utility

```php
Regex::match('#(\d+)#', 'abc123')        // ['123'] | null
Regex::matchAll('#(\w+)#', 'a b c')      // [['a','b','c']]
Regex::replace('#\s+#', '-', 'a b')      // 'a-b'
Regex::split('#,#', 'a,b,c')             // ['a','b','c']
Regex::quote('foo.bar')                  // 'foo\.bar'
Regex::assert('#valid#')                 // throws on bad regex
```
