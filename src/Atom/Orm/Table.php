<?php
declare(strict_types=1);
namespace Atom\Orm;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
final readonly class Table
{
    public function __construct(public string $name) {}
}
