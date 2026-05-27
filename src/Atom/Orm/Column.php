<?php
declare(strict_types=1);
namespace Atom\Orm;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Column
{
    public function __construct(public string $name) {}
}
