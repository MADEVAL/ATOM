<?php
declare(strict_types=1);
namespace Atom;

final readonly class Config
{
    public function __construct(
        public bool $debug = false,
        public string $cacheDir = '',
        public string $viewsDir = '',
    ) {}
}
