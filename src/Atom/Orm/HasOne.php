<?php
declare(strict_types=1);
namespace Atom\Orm;

/** @template TModel of Model */
final class HasOne extends Relation
{
    public function query(): Query
    {
        return new Query($this->db, $this->related);
    }

    /** @return TModel|null */
    protected function load(): mixed
    {
        return $this->query()->where($this->foreignKey, $this->localValue)->first();
    }
}
