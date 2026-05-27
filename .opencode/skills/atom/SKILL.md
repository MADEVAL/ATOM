# Atom Framework

PHP 8.5 micro-framework. Single-regex router, PCRE template engine, DI, validation (18 attribute rules), sessions, database with transactions, logger with rotation, CLI with help & NO_COLOR, .env.

> Topic files: `routing.md`, `http.md`, `templates.md`, `validation.md`, `middleware.md`, `di.md`, `database.md`, `cli.md`, `logger.md`, `test-client.md`, `rate-limit.md`, `encryption.md`

## Scope of application

| Scenario | Fit |
|---|---|
| REST API, microservice, MVP, prototype, hobby project | Excellent — zero deps, single-file deploy |
| Admin panel, static-site backend, SPA backend, server-rendered pages | Good — templates + CSRF + validation |
| High-traffic API | Good — O(1) routing, JIT-compiled PCRE |
| Enterprise CMS | Not suitable — no migrations, no admin generator |
| WebSocket/real-time | Good — built-in event loop, rooms, broadcast |

## Project structure

```
src/Atom/
├── Config.php              # debug, cacheDir, viewsDir, timezone, logFile, logLevel, logMaxSize, appName, fromEnv, get
├── Application.php         # entry point, boot, run
├── Console/Console.php     # CLI: list, help, routes, cache, custom commands, NO_COLOR
├── Container/Container.php # DI: bind, singleton, instance, has, autowire
├── Database/Database.php   # PDO wrapper: all, one, single, run
├── Http/
│   ├── Request.php         # hooks, JSON body, Bearer token, _method, file, validate
│   ├── Response.php        # html, json, redirect, cookies, text, noContent, cache, send
│   ├── Session.php         # get/set, flash, regenerate, csrfToken, validateCsrf
│   ├── StatusCode.php      # enum 200..503
│   └── UploadedFile.php    # typed $_FILES: ok, size, ext, move
├── Middleware/
│   ├── MiddlewareInterface.php
│   ├── Cors.php            # preflight + CORS headers, origin reflection
│   ├── Csrf.php            # CSRF token validation with rotation
│   ├── Pipeline.php        # onion: Closure | object | string
│   └── RateLimit.php       # per-IP request rate limiting
├── Routing/
│   ├── Route.php           # #[Route] attribute
│   ├── CompiledRoute.php   # internal representation
│   ├── RouteCompiler.php   # single PCRE regex
│   └── Router.php          # dispatch, groups, url(), cache, routes(), health()
├── Support/
│   ├── Logger.php          # file logger: 7 levels, rotate, clear, maxSize
│   ├── Regex.php           # PCRE wrapper
│   ├── Paginator.php       # page/perPage/total/pages
│   └── Encrypt.php         # AES-256-GCM encryption
├── Test/
│   └── HttpClient.php      # fluent API test client
├── Validation/
│   └── Validator.php       # 18 attribute rules + ValidationException
└── View/
    ├── Compiler.php         # Twig-like → PHP, nested braces, for-loop shadow restore
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
    debug: false,           // true = rethrow errors in run()
    cacheDir: '/tmp/atom',
    viewsDir: __DIR__ . '/templates',
    timezone: 'UTC',
    logFile: '/var/log/app.log',
    logLevel: 2,            // WARN
    logMaxSize: 1048576,    // 1MB autorotation
    appName: 'MyApp',
    routeCache: 'file',     // 'file' = var_export PHP, 'cache' = framework Cache
    viewCache: 'file',      // 'file' = compiled PHP classes, 'cache' = framework Cache
);
$app = new Application($config);
```

Env vars: `APP_ROUTE_CACHE` (default `file`), `APP_VIEW_CACHE` (default `file`).

## Performance

- Router: **one** `preg_match` per request via `(?|...(*:N))` branch-reset + MARK
- Named routes: O(1) lookup, O(1) URL generation
- 405 Method Not Allowed: O(1) via altRegex
- Routes cache: PHP `var_export` include (no unserialize). `cacheStrategy` option: `file` (default) or `cache` (framework Cache).
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

## WebSocket Server

```php
// Register handler on Application
$app->ws('/chat/{room}', function(Connection $conn, $data, $event, $params) {
    if ($event === 'open') {
        $app->wsServer()->join($params['room'], $conn);
    }
    if ($event === 'message') {
        $app->wsServer()->sendJsonToRoom($params['room'], $data);
    }
});

// Connection API
$conn->id()          // string - unique 32-char hex ID
$conn->isOpen()      // bool
$conn->send('text')  // bool - text frame
$conn->sendJson([])  // bool - JSON frame
$conn->ping()        // bool - keepalive
$conn->close(1000)   // void - close frame
$conn->data          // array - mutable metadata

// Server API (via $app->wsServer())
$server->join('room', $conn)           // Join a room
$server->leave('room', $conn)          // Leave a room
$server->room('room')                  // Connection[]
$server->sendToRoom('room', 'text')    // Broadcast to room
$server->sendJsonToRoom('room', $data) // JSON broadcast to room
$server->broadcast('text')             // All connections
$server->broadcastJson($data)          // All connections JSON

// CLI
php atom ws:serve --port=8080 --host=0.0.0.0
```

Key features: RFC 6455 frame encoding/decoding, non-blocking stream_select event loop, room/channel management, client→server frame masking handled, auto-pong, close handshake, graceful `stop()`.

## Cache

```php
// Access via Application (driver from APP_CACHE_DRIVER env: 'array' | 'file')
$cache = $app->cache();

// Basic operations
$cache->set('key', 'value', ttl: 3600);
$value = $cache->get('key', default: null);
$cache->has('key');         // bool
$cache->delete('key');
$cache->flush();

// Atomic counters
$cache->increment('hits');      // → 1
$cache->increment('hits', 5);   // → 6
$cache->decrement('hits');      // → 5

// Compute-on-miss
$data = $cache->remember('expensive', fn() => fetchFromDb(), ttl: 300);
$data = $cache->rememberForever('config', fn() => loadConfig());

// Manual instantiation
$cache = new Cache(new ArrayDriver());           // in-memory
$cache = new Cache(new FileDriver('/tmp/cache')); // file-based
```

Key features: PSR-16-like API, TTL expiry, atomic file writes (temp+rename), probabilistic expired-entry cleanup, null-safe.

## ORM

```php
use Atom\Orm\{Model, Query, Column, PrimaryKey, Table, HasMany, BelongsTo, HasOne};

#[Table('users')]
class User extends Model {
    #[PrimaryKey] #[Column] public ?int $id = null;
    #[Column] public string $name;
    #[Column] public string $email;
    
    #[HasMany(Comment::class, 'user_id')]
    public function comments(): Query { return $this->hasMany(Comment::class, 'user_id'); }
}

// Query builder
User::query()->where('name', 'LIKE', '%john%')->orderBy('id', 'DESC')->limit(10)->get();
User::query()->find(1);                    // single by primary key
User::query()->whereIn('id', [1,2,3])->get();
User::query()->whereBetween('age', 18, 65)->count();

// CRUD
$user = new User(['name' => 'John', 'email' => 'john@example.com']);
$user->save();          // INSERT
$user->name = 'Jane';
$user->save();          // UPDATE
$user->delete();        // DELETE

// Relations (lazy loading with caching)
$comments = $user->comments()->get();       // hasMany
$user = $comment->belongsTo(User::class);   // belongsTo
$profile = $user->profile()->get();         // hasOne

// Pagination
$page = User::query()->paginate($request, perPage: 15);
// → ['data' => [...], 'page' => 1, 'perPage' => 15, 'total' => 42, 'pages' => 3]
```

Key features: Model attributes autodiscovery via #[Column], lazy-loading relations with per-model caching, query builder with fluent API, pagination from Request.

## Logger

```php
$app->log()->debug('message', ['key' => 'value']);
$app->log()->info('user created', ['id' => 1]);
$app->log()->error('failed', ['exception' => $e]);
```

Logger is automatically registered in the container. Access via `$app->log()` or type-hint `Logger` in constructors.

## Router

```php
// cacheStrategy: 'file' (default, var_export include) | 'cache' (framework Cache abstraction)
$app->router->setCacheStrategy('cache');

// Cache routes
$app->router->cacheRoutes('/tmp/routes.php');
```

## RateLimit

```php
#[RateLimit(max: 100, window: 60)]
public function handle(Request $req): Response { ... }
```

Responses automatically include `X-RateLimit-Limit` and `X-RateLimit-Remaining` headers on 429 and on-handled responses.

## Response

```php
$response->getContent();              // string — response body
$response->getStatusCode();           // int — HTTP status code
$response->getHeader('Content-Type'); // string — header value
$response->withStatus(StatusCode::NOT_FOUND); // clone with new status

// Security headers (automatic on html/json responses):
// X-Content-Type-Options: nosniff
// X-Frame-Options: SAMEORIGIN
```

