<?php
declare(strict_types=1);
namespace Atom\Orm;

use Atom\Database\Database;

/** @template TModel of Model */
abstract class Relation
{
    /** @var list<TModel>|TModel|null */
    protected mixed $results = null;
    protected bool $loaded = false;

    public function __construct(
        protected readonly Database $db,
        /** @var class-string<TModel> */
        protected readonly string $related,
        protected readonly string $foreignKey,
        protected readonly string $localKey,
        protected mixed $localValue,
    ) {}

    /** @return list<TModel>|TModel|null */
    public function getResults(): mixed
    {
        if (!$this->loaded) {
            $this->results = $this->load();
            $this->loaded = true;
        }
        return $this->results;
    }

    /** @return Query<TModel> */
    abstract public function query(): Query;

    /** @return list<TModel>|TModel|null */
    abstract protected function load(): mixed;

    /** @return class-string<TModel> */
    public function relatedClass(): string { return $this->related; }
    public function foreignKey(): string { return $this->foreignKey; }
    public function localKey(): string { return $this->localKey; }
}
