<?php
declare(strict_types=1);
namespace Atom\Routing;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::IS_REPEATABLE)]
final readonly class Route
{
    /** @param string[] $methods */
    public function __construct(
        public string $path,
        public array $methods = ['GET'],
        public string $name = '',
        public array $middleware = [],
    ) {}
}
