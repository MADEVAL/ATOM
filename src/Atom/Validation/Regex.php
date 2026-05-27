<?php
declare(strict_types=1);
namespace Atom\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Regex
{
    public function __construct(public string $pattern, public string $message = 'Invalid format') {}
}
