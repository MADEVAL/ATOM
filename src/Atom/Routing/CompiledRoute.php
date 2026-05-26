<?php
declare(strict_types=1);
namespace Atom\Routing;

final readonly class CompiledRoute
{
    public function __construct(
        public string $path,
        public array $methods,
        public string $name,
        public array $middleware,
        public string $controller,
        public string $action,
    ) {}
}
