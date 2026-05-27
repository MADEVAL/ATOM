<?php
declare(strict_types=1);
namespace Atom\Validation;

use Attribute;

#[Attribute(Attribute::TARGET_PROPERTY)]
final readonly class Ip
{
    public function __construct(
        public string $message = 'Invalid IP address',
        public bool $onlyV4 = false,
        public bool $onlyV6 = false,
        public bool $noReserved = false,
        public bool $noPrivate = false,
        public bool $global = false,
    ) {}
}
