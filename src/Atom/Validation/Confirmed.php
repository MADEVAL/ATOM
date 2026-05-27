<?php
declare(strict_types=1);
namespace Atom\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Confirmed
{
    public function __construct(public string $message = 'Confirmation does not match') {}
}
