# CLI Console

Built-in console commands + custom command registration. ANSI colored output + option parsing via PCRE. `NO_COLOR` support.

## Entry point

The `bin/atom` script is registered via `composer.json` → `vendor/bin/atom`.

```bash
php bin/atom list      # show all commands with descriptions
php bin/atom help      # alias for list
php bin/atom routes    # list registered routes
php bin/atom cache     # clear compiled cache
```

## Registering in Application

Custom commands receive positional args AND named options. Optional description for `help` output.

```php
use Atom\Console\Console;

$console = new Console($app);
$console->add('db:migrate', function (array $args, array $options): int {
    if ($options['force'] ?? false) {
        echo "Force migrating...\n";
    }
    echo "Migrated {$args[0]}\n";
    return 0;
}, 'Run database migrations');
$console->run($argv);
```

## Options

Options parsed via PCRE `#^--([a-zA-Z][a-zA-Z0-9_-]*)(?:=(.+))?$#`:

```bash
php bin/atom db:migrate --force             # $options['force'] = true
php bin/atom db:migrate --env=production     # $options['env'] = 'production'
php bin/atom db:migrate --force --env=prod   # both
```

Exceptions in commands are caught and printed as red error messages.

## Colored output

Methods, route names, status messages use ANSI color codes:
- `bold`, `green`, `yellow`, `cyan`, `red`

### Disabling colors

```bash
NO_COLOR=1 php bin/atom routes    # environment variable
php bin/atom routes --no-color    # command-line flag
```

Both methods strip all ANSI codes from output.
