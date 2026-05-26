# Database

Minimal PDO wrapper with prepared statements.

## Connection

```php
use Atom\Database\Database;

$db = new Database($config->get('DB_DSN'));

// SQLite
$db = new Database('sqlite:/path/to/db.sqlite');

// MySQL
$db = new Database('mysql:host=localhost;dbname=app;charset=utf8mb4', 'user', 'pass');

// Register as singleton
$app->container->singleton(Database::class, fn() => new Database($app->config->get('DB_DSN')));
```

## Methods

```php
// List of rows
$users = $db->all('SELECT * FROM users WHERE active = ?', [1]);

// Single row or null
$user = $db->one('SELECT * FROM users WHERE id = ?', [$id]);

// Scalar value
$count = $db->single('SELECT COUNT(*) FROM users');

// Execute statement, return affected rows
$db->run('UPDATE users SET name = ? WHERE id = ?', ['New Name', 1]);

// Last insert ID
$id = $db->lastId();

// Raw PDO for advanced use
$db->raw()->beginTransaction();
```

## Named parameters

```php
$db->one('SELECT * FROM users WHERE email = :email', ['email' => 'john@test.com']);
```
