# ORM

Attribute-mapped Active Record style ORM with guarded query builder and lazy/eager relations.

## Models

```php
use Atom\Orm\{Model, Table, Column, PrimaryKey};

#[Table('users')]
final class User extends Model {
    #[PrimaryKey] #[Column('id')]
    public ?int $id = null;

    #[Column('email')]
    public string $email = '';
}
```

Use `Model::setConnection($db)` before querying. Public non-static properties are mapped; `#[Column('db_column')]` maps snake_case database columns to PHP property names.

## CRUD

```php
$user = new User();
$user->fill(['email' => 'a@example.com']);
$user->save();

$same = User::find($user->id);
$required = User::findOrFail($user->id);
$existing = User::firstOrCreate(['email' => 'a@example.com']);
User::destroy($user->id);
```

## Query Builder

```php
$users = User::query()
    ->where('email', 'LIKE', '%@example.com')
    ->whereIn('id', [1, 2, 3])
    ->orderBy('id', 'DESC')
    ->limit(10)
    ->get();
```

Identifiers are validated and quoted. Operators are limited to `=`, `!=`, `<>`, `<`, `>`, `<=`, `>=`, `LIKE`, `NOT LIKE`. Empty `whereIn()` matches no rows; empty `whereNotIn()` is a no-op.

## Relations

```php
final class Post extends Model {
    #[Column('user_id')]
    public int $userId = 0;

    public function user(): BelongsTo {
        return $this->belongsTo(User::class, 'user_id');
    }
}

$posts = Post::query()->with('user')->get();
$author = $posts[0]->user()->getResults();
```

Eager loading respects `#[Column]` mappings for `belongsTo`, `hasOne`, and `hasMany`.
