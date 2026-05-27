<?php
declare(strict_types=1);
namespace Atom\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Integer
{
    public function __construct(
        public string $message = 'Must be an integer',
        public ?int $min = null,
        public ?int $max = null,
    ) {}
}
