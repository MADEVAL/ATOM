<?php
declare(strict_types=1);
namespace Atom;

use Atom\Support\Regex;

final readonly class Config
{
    /** @param array<string,string> $env */
    public function __construct(
        public bool $debug = false,
        public string $cacheDir = '',
        public string $viewsDir = '',
        public string $timezone = 'UTC',
        public string $logFile = '',
        public int $logLevel = 0,
        public int $logMaxSize = 0,
        public string $appName = 'Atom',
        /** 'file' = var_export PHP (fast require), 'cache' = framework Cache abstraction */
        public string $routeCache = 'file',
        public string $viewCache = 'file',
        public array $env = [],
    ) {}

    public static function fromEnv(string $path = '.env', bool $setGlobal = true): self
    {
        /** @var array<string,string> $env */
        $env = [];
        self::loadEnvFile($path, $env, $setGlobal);

        $profile = $env['APP_ENV'] ?? getenv('APP_ENV') ?: '';
        if ($profile !== '' && is_file($path . '.' . $profile)) {
            self::loadEnvFile($path . '.' . $profile, $env, $setGlobal);
        }

        $logLevels = ['DEBUG' => 0, 'INFO' => 1, 'WARN' => 2, 'ERROR' => 3, 'CRITICAL' => 4, 'ALERT' => 5, 'EMERGENCY' => 6];
        $level = $logLevels[strtoupper($env['APP_LOG_LEVEL'] ?? '')] ?? 0;

        return new self(
            debug: in_array($env['APP_DEBUG'] ?? '', ['1', 'true'], true),
            cacheDir: $env['APP_CACHE_DIR'] ?? '',
            viewsDir: $env['APP_VIEWS_DIR'] ?? '',
            timezone: $env['APP_TIMEZONE'] ?? 'UTC',
            logFile: $env['APP_LOG_FILE'] ?? '',
            logLevel: $level,
            logMaxSize: (int) ($env['APP_LOG_MAX_SIZE'] ?? 0),
            appName: $env['APP_NAME'] ?? 'Atom',
            routeCache: $env['APP_ROUTE_CACHE'] ?? 'file',
            viewCache: $env['APP_VIEW_CACHE'] ?? 'file',
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

    /**
     * @param array<string,string> $env
     * @param-out array<string,string> $env
     */
    private static function loadEnvFile(string $path, array &$env, bool $setGlobal): void
    {
        if (!is_file($path)) return;
        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) return;
        foreach ($lines as $line) {
            $line = ltrim($line);
            if ($line === '' || $line[0] === '#') continue;
            if ($m = Regex::match('#^([A-Z_][A-Z0-9_]*)\s*=\s*(.*)$#', $line)) {
                $key = (string) $m[1];
                $v = trim((string) $m[2]);
                $dq = Regex::match('#^"((?:[^"\\\\]|\\\\.)*)"$#', $v);
                if ($dq !== null) {
                    $v = stripcslashes((string) $dq[1]);
                } else {
                    $sq = Regex::match("#^'([^']*)'$#", $v);
                    if ($sq !== null) $v = (string) $sq[1];
                }
                $env[$key] = $v;
                if ($setGlobal) {
                    $_ENV[$key] = $v;
                }
            }
        }
    }
}
