# CLI Console

Built-in console commands + custom command registration. ANSI colored output + option parsing via PCRE.

## Entry point

The `bin/atom` script is registered via `composer.json` → `vendor/bin/atom`.

```bash
php bin/atom list      # show all commands
php bin/atom routes    # list registered routes
php bin/atom cache     # clear compiled cache
```

## Registering in Application

Custom commands receive positional args AND named options:

```php
use Atom\Console\Console;

$console = new Console($app);
$console->add('db:migrate', function (array $args, array $options): int {
    if ($options['force'] ?? false) {
        echo "Force migrating...\n";
    }
    echo "Migrated {$args[0]}\n";
    return 0;
});
$console->run($argv);
```

## Options

Options parsed via PCRE `#^--([a-zA-Z][a-zA-Z0-9_-]*)(?:=(.+))?$#`:

```bash
php bin/atom db:migrate --force             # $options['force'] = true
php bin/atom db:migrate --env=production     # $options['env'] = 'production'
php bin/atom db:migrate --force --env=prod   # both
```

## Colored output

Methods, route names, status messages use ANSI color codes:
- `bold`, `green`, `yellow`, `cyan`, `red`

```php
// Custom command in console/ directory
$console->add('db:seed', function (array $args, array $options): int {
    echo "Seeding...\n";
    return 0;
});
```
