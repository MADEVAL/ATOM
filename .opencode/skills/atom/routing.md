# Routing

Single `preg_match` dispatch — all routes compile into one PCRE regex using branch-reset `(?|...)` and `(*MARK)`.

## Route definitions

```php
// Basic
$app->router->get('/users/{id}', 'UserController@show');
$app->router->post('/users', 'UserController@create');
$app->router->put('/items/{id}', 'ItemController@update');
$app->router->patch('/users/{id}', 'UserController@patch');
$app->router->delete('/files/{id}', 'FileController@remove');
$app->router->any('/webhook', 'WebhookController@handle');
$app->router->match(['GET', 'POST'], '/form', 'FormController@handle');
```

## Built-in parameter patterns

| Token | PCRE | Example |
|---|---|---|
| `{id}` | `[0-9]+` | `42` |
| `{slug}` | `[a-z0-9\-]+` | `hello-world` |
| `{any}` | `[^/]+` | `anything` |
| `{all}` | `.+` | `path/to/file` |
| `{name:custom}` | `custom` | inline PCRE |

## Custom regex parameters

```php
$app->router->get('/users/{id:\d+}', 'UserController@show');
$app->router->get('/files/{hash:[a-f0-9]{32}}', 'FileController@download');
$app->router->addPattern('uuid', '[a-f0-9-]{36}');
$app->router->get('/items/{uuid}', 'ItemController@show');
```

## Groups & middleware

```php
$app->router->group('/admin', ['Auth', 'AdminOnly'], fn($r) => {
    $r->get('/dashboard', 'AdminController@index');
    $r->group('/users', ['Throttle'], fn($r) => {
        $r->get('/{id}', 'AdminController@edit');
    });
});
```

## Named routes & URL generation

```php
$app->router->get('/users/{id}', 'UserController@show', 'user.show');
echo $app->router->url('user.show', ['id' => 42]); // /users/42
```

## Attribute-based routing

```php
use Atom\Routing\Route;

class ApiController {
    #[Route('/api/items', ['GET'], 'items.list')]
    #[Route('/api/items/{id}', ['GET', 'DELETE'], 'items.crud')]
    public function handle(Request $request): string { ... }
}
$app->router->loadFromAttributes(__DIR__ . '/Controllers');
```

## How it works

All routes compile into ONE regex:

```
#^(?|(?<METHOD>GET)/users/(?<id>[0-9]+)(*:0)|(?<METHOD>POST)/users(*:1)|...)$#xs
```

One `preg_match` returns `$m['MARK']` → route ID, `$m['id']` → param value. JIT-compiled, handles 10 000 routes in a single call.
