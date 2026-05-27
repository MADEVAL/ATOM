<?php
declare(strict_types=1);
namespace Atom\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class FloatVal
{
    public function __construct(
        public string $message = 'Must be a float',
        public ?float $min = null,
        public ?float $max = null,
    ) {}
}
