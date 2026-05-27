<?php
declare(strict_types=1);
namespace Atom\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Max
{
    public function __construct(public int $value, public string $message = 'Too long') {}
}
