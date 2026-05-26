# Logger

File-based logging with PSR-compatible levels.

## Usage

```php
use Atom\Support\Logger;

$log = new Logger('/var/log/app.log');
$log->debug('query executed', ['sql' => $sql, 'ms' => 12.5]);
$log->info('user logged in', ['id' => 42]);
$log->warn('rate limit approaching', ['ip' => $req->ip]);
$log->error('payment failed', ['order' => 123, 'error' => $e->getMessage()]);
$log->critical('disk full', ['free' => 0]);
$log->alert('database down', ['host' => 'db1']);
$log->emergency('complete outage');
```

## Minimum level

Messages below the minimum level are silently dropped:

```php
$log = new Logger('/var/log/app.log', Logger::WARN);
$log->debug('ignored');   // dropped
$log->info('also silent'); // dropped
$log->warn('logged');      // written
$log->error('logged');     // written
$log->critical('logged');  // written
```

## Log format

```
[2026-05-26 18:30:00] INFO: user logged in {"id":42}
[2026-05-26 18:30:01] ERROR: payment failed {"order":123}
[2026-05-26 18:30:02] CRITICAL: disk full {"free":0}
```

Levels: `DEBUG`(0), `INFO`(1), `WARN`(2), `ERROR`(3), `CRITICAL`(4), `ALERT`(5), `EMERGENCY`(6). Writes atomically with `LOCK_EX`.
