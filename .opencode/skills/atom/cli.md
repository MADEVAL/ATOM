# CLI Console

Built-in console commands + custom command registration.

## Entry point

The `bin/atom` script is registered via `composer.json` → `vendor/bin/atom`.

```bash
php bin/atom list      # show all commands
php bin/atom routes    # list registered routes
php bin/atom cache     # clear compiled cache
```

## Registering in Application

```php
use Atom\Console\Console;

$console = new Console($app);
$console->add('db:migrate', function (array $args): int {
    // run migrations
    echo "Migrated.\n";
    return 0;
});
$console->run($argv);
```

## Custom command in console/ directory

```php
// console/db.php
$console->add('db:seed', function (array $args): int {
    echo "Seeding...\n";
    return 0;
});
```
