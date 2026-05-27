<?php
declare(strict_types=1);
namespace Atom\Orm;

use Atom\Database\Database;
use Atom\Support\Regex;
use Attribute;
use ReflectionClass;

abstract class Model
{
    private static ?Database $connection = null;
    /** @var array<string,array{pk:string,table:string,cols:array<string,string>,props:array<string,string>}> */
    private static array $meta = [];
    private array $original = [];
    private bool $exists = false;
    /** @var array<string,Relation> */
    private array $relations = [];
    private bool $timestamps = true;

    // ──────────────────────────── Static ────────────────────────────

    public static function setConnection(Database $db): void { self::$connection = $db; }
    protected static function db(): Database { return self::$connection ?? throw new \RuntimeException('ORM connection not set. Call Model::setConnection($db) first.'); }

    public static function table(): string
    {
        $cls = static::class;
        return self::meta($cls)['table'];
    }

    public static function primaryKey(): string
    {
        $cls = static::class;
        return self::meta($cls)['pk'];
    }

    /** @return array{pk:string,table:string,cols:array<string,string>,props:array<string,string>} */
    private static function meta(string $class): array
    {
        if (isset(self::$meta[$class])) {
            return self::$meta[$class];
        }
        $ref = new ReflectionClass($class);
        $table = '';
        foreach ($ref->getAttributes(Table::class) as $attr) {
            $table = $attr->newInstance()->name;
        }
        if ($table === '') {
            $table = self::defaultTable($ref->getShortName());
        }
        $pk = 'id';
        $cols = [];
        $props = [];
        foreach ($ref->getProperties(\ReflectionProperty::IS_PUBLIC) as $prop) {
            if ($prop->isStatic()) continue;
            $name = $prop->getName();
            $col = $name;
            foreach ($prop->getAttributes(Column::class) as $attr) {
                $col = $attr->newInstance()->name;
            }
            foreach ($prop->getAttributes(PrimaryKey::class) as $attr) {
                $pk = $col;
            }
            $cols[$name] = $col;
            $props[$col] = $name;
        }
        return self::$meta[$class] = ['pk' => $pk, 'table' => $table, 'cols' => $cols, 'props' => $props];
    }

    /** @return static */
    public static function query(): Query
    {
        return new Query(static::db(), static::class);
    }

    // ──────────────────────────── CRUD ────────────────────────────

    /** @return static|null */
    public static function find(mixed $id): ?static
    {
        return static::query()->where(static::primaryKey(), $id)->first();
    }

    /** @return static */
    public static function findOrFail(mixed $id): static
    {
        return static::query()->where(static::primaryKey(), $id)->firstOrFail();
    }

    /** @return static */
    public static function create(array $attributes): static
    {
        $model = new static();
        $model->fill($attributes);
        $model->save();
        return $model;
    }

    /** @return static */
    public static function firstOrCreate(array $search, array $attrs = []): static
    {
        $q = static::query();
        foreach ($search as $k => $v) $q->where($k, $v);
        $found = $q->first();
        return $found ?? static::create($search + $attrs);
    }

    public static function destroy(int ...$ids): int
    {
        $count = 0;
        foreach ($ids as $id) {
            $m = static::find($id);
            if ($m) { $m->delete(); $count++; }
        }
        return $count;
    }

    /** @param array<string,mixed> $data */
    public function fill(array $data): void
    {
        $meta = self::meta(static::class);
        foreach ($data as $key => $value) {
            if (isset($meta['cols'][$key])) {
                $this->{$key} = $this->cast($key, $value);
            } elseif (isset($meta['props'][$key])) {
                $prop = $meta['props'][$key];
                $this->{$prop} = $this->cast($prop, $value);
            }
        }
    }

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        $meta = self::meta(static::class);
        $pk = $meta['pk'];
        $data = [];
        foreach ($meta['cols'] as $prop => $col) {
            if ($col === $pk) continue;
            $data[$prop] = $this->{$prop};
        }
        return $data;
    }
    public function save(): bool
    {
        $data = $this->toRow();
        $pk = static::primaryKey();
        $pkVal = $this->{$pk} ?? null;

        if ($this->timestamps) {
            $now = date('Y-m-d H:i:s');
            if (!$this->exists && $this->hasProp('created_at')) {
                $data['created_at'] = $now;
            }
            if ($this->hasProp('updated_at')) {
                $data['updated_at'] = $now;
            }
        }

        if ($this->exists && $pkVal !== null) {
            $sets = implode(', ', array_map(fn($k) => "\"{$k}\" = :{$k}", array_keys($data)));
            $data['__pk'] = $pkVal;
            static::db()->run(
                'UPDATE ' . static::table() . " SET {$sets} WHERE \"{$pk}\" = :__pk",
                $data,
            );
            $this->syncTimestamps($data);
        } else {
            // Exclude primary key from INSERT when it has default value
            $insertData = $data;
            if (($pkVal === 0 || $pkVal === null) && array_key_exists($pk, $insertData)) {
                unset($insertData[$pk]);
            }
            $cols = implode(', ', array_map(fn($k) => "\"{$k}\"", array_keys($insertData)));
            $vals = implode(', ', array_map(fn($k) => ":{$k}", array_keys($insertData)));
            static::db()->run(
                'INSERT INTO ' . static::table() . " ({$cols}) VALUES ({$vals})",
                $insertData,
            );
            $newId = static::db()->lastId();
            if ($newId !== false && !$this->exists) {
                $this->{$pk} = $this->castBack($pk, $newId);
            }
            $this->syncTimestamps($insertData);
        }
        $this->exists = true;
        $this->original = $this->toArray();
        return true;
    }

    public function delete(): bool
    {
        if (!$this->exists) return false;
        $pk = static::primaryKey();
        static::db()->run(
            'DELETE FROM ' . static::table() . " WHERE \"{$pk}\" = :id",
            ['id' => $this->{$pk}],
        );
        $this->exists = false;
        return true;
    }

    public function exists(): bool { return $this->exists; }

    /** @return array<string,mixed> */
    private function toRow(): array
    {
        $meta = self::meta(static::class);
        $row = [];
        foreach ($meta['cols'] as $prop => $col) {
            if ($col === $meta['pk']) {
                $val = $this->{$prop};
                if ($val !== null) $row[$col] = $val;
                continue;
            }
            $row[$col] = $this->{$prop} ?? null;
        }
        return $row;
    }

    /** @param array<string,mixed> $row @return static */
    public static function hydrateRow(array $row): static
    {
        $model = new static();
        $meta = self::meta(static::class);
        foreach ($meta['cols'] as $prop => $col) {
            if (array_key_exists($col, $row)) {
                $model->{$prop} = $model->castBack($prop, $row[$col]);
            }
        }
        $model->exists = true;
        $model->original = $row;
        return $model;
    }

    // ──────────────────────────── Relations ────────────────────────────

    /** @return HasMany<static> */
    protected function hasMany(string $related, string $foreignKey = '', string $localKey = ''): HasMany
    {
        $rel = new HasMany(
            static::db(),
            $related,
            $foreignKey !== '' ? $foreignKey : self::defaultForeignKey(static::class),
            $localKey !== '' ? $localKey : static::primaryKey(),
            $this->{static::primaryKey()},
        );
        return $rel;
    }

    /** @return BelongsTo<static> */
    protected function belongsTo(string $related, string $column = '', string $ownerKey = ''): BelongsTo
    {
        $col = $column !== '' ? $column : self::defaultForeignKey($related);
        return new BelongsTo(
            static::db(),
            $related,
            $col,
            $ownerKey !== '' ? $ownerKey : (new $related())::primaryKey(),
            $this->propForColumn($col) !== null ? ($this->{$this->propForColumn($col)} ?? null) : null,
        );
    }

    /** @return HasOne<static> */
    protected function hasOne(string $related, string $foreignKey = '', string $localKey = ''): HasOne
    {
        return new HasOne(
            static::db(),
            $related,
            $foreignKey !== '' ? $foreignKey : self::defaultForeignKey(static::class),
            $localKey !== '' ? $localKey : static::primaryKey(),
            $this->{static::primaryKey()},
        );
    }

    /** @param list<static> $models */
    public static function eagerLoadRelation(Database $db, array $models, string $name): void
    {
        if ($models === []) return;
        $first = $models[0];
        $rel = $first->{$name}();
        $relatedClass = $rel->relatedClass();
        $fk = $rel->foreignKey();
        $lk = $rel->localKey();
        $ids = array_unique(array_map(fn(Model $m) => $m->{$lk}, $models));

        $q = new Query($db, $relatedClass);
        $q->whereIn($fk, $ids);
        $related = $q->get();

        $grouped = [];
        foreach ($related as $r) {
            $grouped[$r->{$fk}][] = $r;
        }
        foreach ($models as $m) {
            $key = $m->{$lk};
            $m->setLoaded($name, $grouped[$key] ?? []);
        }
    }

    /** @param list<Model> $data */
    private function setLoaded(string $name, array $data): void
    {
        $this->relations[$name] = $data;
    }

    // ──────────────────────────── Helpers ────────────────────────────

    private function cast(string $prop, mixed $value): mixed
    {
        $ref = new ReflectionClass($this);
        if (!$ref->hasProperty($prop)) return $value;
        $type = $ref->getProperty($prop)->getType();
        if ($type instanceof \ReflectionNamedType) {
            return match ($type->getName()) {
                'int'   => (int) $value,
                'float' => (float) $value,
                'bool'  => (bool) $value,
                'string'=> (string) $value,
                default => $value,
            };
        }
        return $value;
    }

    private function castBack(string $prop, mixed $dbValue): mixed
    {
        return $this->cast($prop, $dbValue);
    }

    private function hasProp(string $column): bool
    {
        return isset(self::meta(static::class)['props'][$column]);
    }

    private function propForColumn(string $column): ?string
    {
        return self::meta(static::class)['props'][$column] ?? null;
    }

    private function syncTimestamps(array $data): void
    {
        foreach (['created_at', 'updated_at'] as $col) {
            $prop = $this->propForColumn($col);
            if ($prop !== null && isset($data[$col])) {
                $this->{$prop} = $data[$col];
            }
        }
    }

    private static function defaultTable(string $shortName): string
    {
        return strtolower((string) Regex::replace('#([A-Z])#', '_$1', lcfirst($shortName))) . 's';
    }

    private static function defaultForeignKey(string $class): string
    {
        $table = self::meta($class)['table'];
        return rtrim($table, 's') . '_id';
    }
}
