<?php
declare(strict_types=1);
namespace Atom\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class In
{
    /** @param list<string|int> $values */
    public function __construct(public array $values, public string $message = 'Invalid value') {}
}
