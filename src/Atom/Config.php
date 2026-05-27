<?php
declare(strict_types=1);
namespace Atom;

use Atom\Support\Regex;

final readonly class Config
{
    public function __construct(
        public bool $debug = false,
        public string $cacheDir = '',
        public string $viewsDir = '',
        /** @var array<string,string> */
        public array $env = [],
    ) {}

    public static function fromEnv(string $path = '.env', bool $setGlobal = true): self
    {
        $env = [];
        if (is_file($path)) {
            foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
                $line = ltrim($line);
                if ($line === '' || $line[0] === '#') continue;
                if ($m = Regex::match('#^([A-Z_][A-Z0-9_]*)\s*=\s*(.*)$#', $line)) {
                    $v = trim($m[2]);
                    $dq = Regex::match('#^"((?:[^"\\\\]|\\\\.)*)"$#', $v);
                    if ($dq !== null) {
                        $v = stripcslashes($dq[1]);
                    } else {
                        $sq = Regex::match("#^'([^']*)'$#", $v);
                        if ($sq !== null) $v = $sq[1];
                    }
                    $env[$m[1]] = $v;
                    if ($setGlobal) {
                        $_ENV[$m[1]] = $v;
                    }
                }
            }
        }
        return new self(
            debug: in_array($env['APP_DEBUG'] ?? '', ['1', 'true'], true),
            cacheDir: $env['APP_CACHE_DIR'] ?? '',
            viewsDir: $env['APP_VIEWS_DIR'] ?? '',
            env: $env,
        );
    }

    public function get(string $key, string $default = ''): string
    {
        if (array_key_exists($key, $this->env)) {
            return $this->env[$key];
        }
        $v = getenv($key, true);
        return $v !== false ? $v : $default;
    }
}
