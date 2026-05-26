# Atom Framework

PHP 8.5 micro-framework. Single-regex router, PCRE-based template engine, minimal DI container. 388 lines, 15 classes, 100% test coverage.

## Project structure

```
src/Atom/
├── Config.php              # debug, cacheDir, viewsDir
├── Application.php         # entry point, boot, run
├── Container/
│   └── Container.php       # DI: bind, singleton, instance, autowire
├── Http/
│   ├── Request.php         # request: hooks, JSON body, Bearer token, _method
│   ├── Response.php        # response: html, json, redirect, cookies
│   └── StatusCode.php      # enum 200..500
├── Middleware/
│   └── Pipeline.php        # onion middleware: Closure | object | string
├── Routing/
│   ├── Route.php           # #[Route] attribute
│   ├── CompiledRoute.php   # internal route representation
│   ├── MatchedRoute.php    # match result
│   ├── RouteCompiler.php   # compiles routes to single PCRE regex
│   └── Router.php          # dispatch, groups, URL generation, cache
├── Support/
│   └── Regex.php           # PCRE wrapper
└── View/
    ├── Compiler.php         # Twig-like → PHP compiler
    ├── Engine.php           # render, filters, globals
    └── Template.php         # base template class

public/index.php             # front controller
views/                       # .twig templates
storage/cache/               # compiled routes & templates
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

## Routing

### Single PCRE dispatch — all routes in one `preg_match`

```php
$app->router->get('/users/{id}', 'UserController@show');
$app->router->group('/admin', ['auth'], fn($r) => {
    $r->get('/dashboard', 'AdminController@index');
    $r->post('/users/{id:\d+}', 'AdminController@update');
});
```

Built-in patterns: `{id}` = `[0-9]+`, `{slug}` = `[a-z0-9\-]+`, `{any}` = `[^/]+`, `{all}` = `.+`.
Custom: `{code:[A-Z]{3}}`.

### Named routes & URL generation

```php
$app->router->get('/users/{id}', 'UserController@show', 'user.show');
echo $app->router->url('user.show', ['id' => 42]); // /users/42
```

### Attribute-based routing

```php
class ApiController {
    #[Route('/api/items', ['GET'], 'items.list')]
    #[Route('/api/items/{id}', ['GET', 'DELETE'], 'items.crud')]
    public function handle(Request $request): string { ... }
}
$app->router->loadFromAttributes(__DIR__ . '/Controllers');
```

## Controllers

Return string (auto-wrapped to HTML response) or `Response` object:

```php
class UserController {
    public function show(string $id, Request $request): Response {
        $user = findUser($id);
        return Response::json($user);
    }
    public function __invoke(): string {
        return 'Hello World';
    }
}
```

## Request

Property hooks with PHP 8.5 syntax:

```php
$req->method   // GET|POST|... (supports _method spoofing)
$req->path     // /api/users
$req->uri      // /api/users?id=1
$req->scheme   // http|https
$req->host     // example.com
$req->ip       // 127.0.0.1
$req->isAjax   // true|false
$req->accept   // application/json
$req->bearer   // Bearer token from Authorization header
$req->input('key', 'default')
$req->header('Content-Type')
$req->wantsJson()
```

JSON body auto-parses when `Content-Type: application/json`.
Method spoofing: POST with `_method=PUT` → `$req->method === 'PUT'`.

## Response

```php
Response::html('<h1>Hello</h1>')
Response::json(['key' => 'value'], pretty: true)
Response::redirect('/login')
Response::json($data, StatusCode::CREATED)

(new Response('body'))
    ->withHeader('X-Custom', 'value')
    ->withStatus(StatusCode::NOT_FOUND)
    ->withCookie('session', $token, ttl: 7200, path: '/')
    ->send();
```

Header injection blocked via `Regex::replace('#[\r\n]+#')`.
Cookies sent with `httponly: true, samesite: Lax` by default.

## Templates (Twig-like syntax)

### Variables & filters

```twig
{{ user.name | e }}
{{ title | upper | raw }}
{{ count | default(0) }}
{{ data | json }}
{{ items.0 }}
```

### Control flow

```twig
{% if user.admin %}
  Admin panel
{% elseif user.active %}
  Dashboard
{% else %}
  Inactive
{% endif %}

{% for item in items %}
  <li>{{ item }}</li>
{% endfor %}

{% include "partials/nav.twig" %}
```

### Template inheritance

```twig
{# layout.twig #}
<html>
<head><title>{% block title %}Default{% endblock %}</title></head>
<body>{% block body %}{% endblock %}</body>
</html>

{# page.twig #}
{% extends "layout.twig" %}
{% block title %}{{ page.title | e }}{% endblock %}
{% block body %}<p>{{ page.content | raw }}</p>{% endblock %}
```

### Custom filters

```php
$app->view->addFilter('markdown', fn($v) => parseMarkdown($v));
$app->view->addGlobal('app_name', 'MyApp');
```

## Middleware

```php
class Auth implements MiddlewareInterface {
    public function handle(Request $req, Closure $next): Response {
        if (!$req->bearer) return new Response('Unauthorized', StatusCode::UNAUTHORIZED);
        return $next($req);
    }
}

// As object
$app->router->get('/admin', 'AdminController@index', '', ['Auth']);

// As Closure
$mw = fn(Request $req, Closure $next) => $next($req)->withHeader('X-Debug', '1');
$app->router->get('/debug', 'DebugController@index', '', [$mw]);

// As container key (resolved via DI)
$app->container->bind('Auth', fn() => new AuthMiddleware);
$app->router->get('/secure', 'SecureController@index', '', ['Auth']);
```

## Container (DI)

```php
$app->container->bind(LoggerInterface::class, FileLogger::class);
$app->container->singleton(Database::class, fn($c) => new Database($c['config']['db']));
$app->container->instance('config', ['db' => ['host' => 'localhost']]);

// Autowiring — resolves constructor dependencies recursively
class UserService {
    public function __construct(private Database $db, private LoggerInterface $log) {}
}
$service = $app->container->make(UserService::class);
```

## Regex utility

```php
Regex::match('#(\d+)#', 'abc123')        // ['123'] | null
Regex::matchAll('#(\w+)#', 'a b c')      // [['a','b','c']]
Regex::replace('#\s+#', '-', 'a b')      // 'a-b'
Regex::split('#,#', 'a,b,c')             // ['a','b','c']
Regex::quote('foo.bar')                  // 'foo\.bar'
Regex::assert('#valid#')                 // throws on bad regex
```

## Performance

- Router: **one** `preg_match` per request via `(?|...(*:N))` branch-reset + MARK
- Routes cache: PHP `var_export` include file (no unserialize)
- Templates: compile to PHP classes, cache on disk, OPCache-friendly
- Property hooks: zero-overhead computed properties

## Config

```php
$config = new Config(
    debug: false,        // true = show stack traces on errors
    cacheDir: '/tmp/atom',
    viewsDir: __DIR__ . '/templates',
);
$app = new Application($config);
```
