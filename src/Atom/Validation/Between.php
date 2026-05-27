<?php
declare(strict_types=1);
namespace Atom\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Between
{
    public function __construct(public int $min, public int $max, public string $message = 'Out of range') {}
}
