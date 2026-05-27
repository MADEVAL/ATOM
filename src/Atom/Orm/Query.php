<?php
declare(strict_types=1);
namespace Atom\Orm;

use Atom\Database\Database;
use Atom\Http\Request;
use Atom\Support\{Regex, Paginator};

final class Query
{
    private string $table;
    private array $wheres = [];
    private array $bindings = [];
    /** @var list<string> */
    private array $orders = [];
    private int $limitVal = 0;
    private int $offsetVal = 0;
    private string $modelClass;
    /** @var list<string> */
    private array $with = [];

    public function __construct(
        private readonly Database $db,
        string $modelClass,
    ) {
        $this->table = $modelClass::table();
        $this->modelClass = $modelClass;
    }

    /** @return $this */
    public function where(string $column, mixed $operator, mixed $value = null): self
    {
        if ($value === null && func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        $colon = $this->bindCol($column);
        $this->wheres[] = ['AND', "{$this->quote($column)} {$operator} {$colon}"];
        $this->bindings[] = $value;
        return $this;
    }

    /** @return $this */
    public function orWhere(string $column, mixed $operator, mixed $value = null): self
    {
        if ($value === null && func_num_args() === 2) {
            $value = $operator;
            $operator = '=';
        }
        $colon = $this->bindCol($column);
        $this->wheres[] = ['OR', "{$this->quote($column)} {$operator} {$colon}"];
        $this->bindings[] = $value;
        return $this;
    }

    /** @param list<int|string> $values @return $this */
    public function whereIn(string $column, array $values): self
    {
        $placeholders = implode(', ', array_map(fn($v) => $this->bind($v), $values));
        $this->wheres[] = ['AND', "{$this->quote($column)} IN ({$placeholders})"];
        return $this;
    }

    /** @param list<int|string> $values @return $this */
    public function whereNotIn(string $column, array $values): self
    {
        $placeholders = implode(', ', array_map(fn($v) => $this->bind($v), $values));
        $this->wheres[] = ['AND', "{$this->quote($column)} NOT IN ({$placeholders})"];
        return $this;
    }

    /** @return $this */
    public function whereNull(string $column): self
    {
        $this->wheres[] = ['AND', "{$this->quote($column)} IS NULL"];
        return $this;
    }

    /** @return $this */
    public function whereNotNull(string $column): self
    {
        $this->wheres[] = ['AND', "{$this->quote($column)} IS NOT NULL"];
        return $this;
    }

    /** @return $this */
    public function whereBetween(string $column, mixed $min, mixed $max): self
    {
        $p1 = $this->bind($min);
        $p2 = $this->bind($max);
        $this->wheres[] = ['AND', "{$this->quote($column)} BETWEEN {$p1} AND {$p2}"];
        return $this;
    }

    /** @return $this */
    public function orderBy(string $column, string $direction = 'ASC'): self
    {
        $this->orders[] = "{$this->quote($column)} " . strtoupper($direction);
        return $this;
    }

    /** @return $this */
    public function orderByDesc(string $column): self
    {
        return $this->orderBy($column, 'DESC');
    }

    /** @return $this */
    public function limit(int $n): self
    {
        $this->limitVal = $n;
        return $this;
    }

    /** @return $this */
    public function offset(int $n): self
    {
        $this->offsetVal = $n;
        return $this;
    }

    /** @param list<string> $relations @return $this */
    public function with(string ...$relations): self
    {
        $this->with = $relations;
        return $this;
    }

    /** @return list<Model> */
    public function get(): array
    {
        $sql = $this->toSql();
        $rows = $this->db->all($sql, $this->bindings);
        return $this->hydrate($rows);
    }

    public function first(): ?Model
    {
        return $this->limit(1)->get()[0] ?? null;
    }

    /** @throws \RuntimeException */
    public function firstOrFail(): Model
    {
        return $this->first() ?? throw new \RuntimeException("No {$this->modelClass} record found");
    }

    public function count(): int
    {
        $sql = 'SELECT COUNT(*) FROM ' . $this->quote($this->table) . $this->whereClause();
        return (int) $this->db->single($sql, $this->bindings);
    }

    public function exists(): bool
    {
        return $this->count() > 0;
    }

    /**
     * @param \Atom\Http\Request|null $request If null, reads page from $_GET
     * @return \Atom\Support\Paginator
     */
    public function paginate(int $perPage = 20, ?Request $request = null): Paginator
    {
        $total = $this->count();
        if ($request !== null) {
            $paginator = Paginator::from($request, $perPage);
        } else {
            $page = max(1, (int) ($_GET['page'] ?? 1));
            $paginator = Paginator::make($page, $perPage);
        }

        $items = $this->limit($paginator->perPage)->offset($paginator->offset)->get();
        $itemsArr = array_map(fn(Model $m) => $m->toArray(), $items);

        $paginator->paginate($itemsArr, $total);
        return $paginator;
    }

    public function __call(string $name, array $args): mixed
    {
        if (preg_match('#^where([A-Z].+)$#', $name, $m)) {
            $column = $this->snake($m[1]);
            return $this->where($column, $args[0] ?? throw new \InvalidArgumentException("Missing value for {$name}"));
        }
        throw new \RuntimeException("Unknown method: {$name}");
    }

    private function quote(string $name): string
    {
        if (str_contains($name, '.')) return $name;
        return '"' . $name . '"';
    }

    private function snake(string $camel): string
    {
        return ltrim(strtolower((string) Regex::replace('#([A-Z])#', '_$1', $camel)), '_');
    }

    private function bindCol(string $col): string
    {
        $name = preg_replace('/[^a-zA-Z0-9_]/', '_', $col);
        $key = ':' . $name . '_' . count($this->bindings);
        return $key;
    }

    private function bind(mixed $value): string
    {
        $key = ':p_' . count($this->bindings);
        $this->bindings[] = $value;
        return $key;
    }

    private function toSql(): string
    {
        $cols = $this->quote($this->table) . '.*';
        $from = $this->quote($this->table);
        $sql = "SELECT {$cols} FROM {$from}" . $this->whereClause();

        if ($this->orders !== []) {
            $sql .= ' ORDER BY ' . implode(', ', $this->orders);
        }
        if ($this->limitVal > 0) {
            $sql .= ' LIMIT ' . $this->limitVal;
        }
        if ($this->offsetVal > 0) {
            $sql .= ' OFFSET ' . $this->offsetVal;
        }
        return $sql;
    }

    private function whereClause(): string
    {
        if ($this->wheres === []) return '';
        $clause = ' WHERE ';
        foreach ($this->wheres as $i => [$bool, $sql]) {
            $clause .= ($i > 0 ? " {$bool} " : '') . $sql;
        }
        return $clause;
    }

    /** @param list<array<string,mixed>> $rows @return list<Model> */
    private function hydrate(array $rows): array
    {
        $models = array_map(fn(array $row) => $this->modelClass::hydrateRow($row), $rows);
        $this->eagerLoad($models);
        return $models;
    }

    /** @param list<Model> $models */
    private function eagerLoad(array $models): void
    {
        foreach ($this->with as $relation) {
            ($this->modelClass)::eagerLoadRelation($this->db, $models, $relation);
        }
    }
}
