<?php
declare(strict_types=1);
namespace Atom\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Email
{
    public function __construct(
        public string $message = 'Invalid email',
        public bool $unicode = true,
    ) {}
}
