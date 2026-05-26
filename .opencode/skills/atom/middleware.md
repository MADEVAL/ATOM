# Middleware

Onion-style pipeline: Closure | object | string. Order: outer → inner → core → back.

## MiddlewareInterface

```php
use Atom\Middleware\MiddlewareInterface;

class Auth implements MiddlewareInterface {
    public function handle(Request $req, Closure $next): Response {
        if (!$req->bearer) {
            return new Response('Unauthorized', StatusCode::UNAUTHORIZED);
        }
        return $next($req);
    }
}
```

## Three forms

```php
// Object instance
$app->router->get('/admin', 'AdminController@index', '', [new AuthMiddleware]);

// String name — resolved via DI container
$app->container->bind('Auth', fn() => new AuthMiddleware);
$app->router->get('/secure', 'Controller@action', '', ['Auth']);

// Closure — inline
$app->router->get('/debug', 'Controller@action', '', [
    fn(Request $req, Closure $next) => $next($req)->withHeader('X-Debug', '1')
]);
```

## CORS middleware

```php
use Atom\Middleware\Cors;

// Default: allow *, all methods, Content-Type + Authorization
$app->router->group('/api', [Cors::class], fn($r) => { ... });

// Custom
$app->router->group('/api', [new Cors(
    allowOrigin: 'https://example.com',
    allowMethods: 'GET,POST,PATCH',
    allowHeaders: 'X-API-Key,Content-Type',
)], fn($r) => { ... });
```

Automatically handles OPTIONS preflight with 204.

## CSRF middleware

```php
use Atom\Middleware\Csrf;

// Registers + autowires Session via DI
$app->router->group('', [Csrf::class], fn($r) => { ... });

// In template: <input type="hidden" name="_csrf" value="{{ _csrf }}">
// Or header: X-CSRF-Token: <token>
// Validated on POST/PUT/PATCH/DELETE — returns 403 on mismatch
```
