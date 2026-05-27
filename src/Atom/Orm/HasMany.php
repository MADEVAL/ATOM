<?php
declare(strict_types=1);
namespace Atom\Orm;

/** @template TModel of Model */
final class HasMany extends Relation
{
    public function query(): Query
    {
        return new Query($this->db, $this->related);
    }

    /** @return list<TModel> */
    protected function load(): mixed
    {
        return $this->query()->where($this->foreignKey, $this->localValue)->get();
    }
}
