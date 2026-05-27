<?php
declare(strict_types=1);
namespace Atom\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY | Attribute::IS_REPEATABLE)]
final readonly class Each
{
    /** @param class-string $class */
    public function __construct(public string $class, public string $message = 'Invalid items') {}
}
