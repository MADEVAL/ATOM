# Container (DI)

Minimal dependency injection: bind, singleton, instance, has, autowire.

## Bindings

```php
// Interface to implementation
$app->container->bind(LoggerInterface::class, FileLogger::class);

// Factory callback (receives container + params)
$app->container->bind('mailer', fn(Container $c, array $params) => new Mailer(...));
```

## Singletons

Same instance every time:

```php
$app->container->singleton(Database::class, fn() => new Database($config->get('DB_DSN')));
```

## Pre-built instances

```php
$app->container->instance('config', $config);
$app->container->instance(Request::class, $request);
```

## Checking registrations

```php
$app->container->has(Database::class); // true|false
```

## Autowiring

Recursive constructor resolution. Supports interfaces when a binding is registered.

```php
class UserService {
    public function __construct(
        private Database $db,
        private LoggerInterface $log,
    ) {}
}

$service = $app->container->make(UserService::class);
// Database and LoggerInterface auto-resolved
```

Parameters are resolved via: explicit params → type-hinted class/interface → default value → error.

## Built-in instances

Application auto-registers:
- `Application::class` → `$app`
- `Container::class` → `$app->container`
- `Router::class` → `$app->router`
- `ViewEngine::class` → `$app->view`
- `Config::class` → `$app->config`
- `Session::class` → lazy singleton (no `session_start()` until first use)
- `Request::class` → current request (per-request, preserved if pre-registered)
