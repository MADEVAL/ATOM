<?php
declare(strict_types=1);
namespace Atom\Routing;

final readonly class MatchedRoute
{
    public function __construct(
        public Route $route,
        public array $params,
        public string $controller,
        public string $action,
    ) {}
}
