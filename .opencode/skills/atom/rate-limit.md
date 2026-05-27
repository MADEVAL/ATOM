# Rate Limiter

Per-IP request rate limiting middleware. Uses in-memory store.

## Usage

```php
use Atom\Middleware\RateLimit;

// Default: 60 requests per 60 seconds
$app->router->group('/api', [RateLimit::class], fn($r) => { ... });

// Custom limits
$app->router->group('/api', [new RateLimit(max: 100, window: 120)], fn($r) => {
    $r->get('/data', 'ApiController@data');
});

// Strict limit for auth endpoints
$app->router->group('/auth', [new RateLimit(max: 5, window: 60)], fn($r) => {
    $r->post('/login', 'AuthController@login');
});
```

Exceeded limit → `429 Too Many Requests`. Limits are tracked per IP + path combination.
