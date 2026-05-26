<?php
declare(strict_types=1);
namespace Atom\Support;

final class Logger
{
    public const DEBUG = 0;
    public const INFO  = 1;
    public const WARN  = 2;
    public const ERROR = 3;

    private static array $levels = ['DEBUG', 'INFO', 'WARN', 'ERROR'];

    public function __construct(
        private string $file,
        private int $minLevel = self::DEBUG,
    ) {}

    public function debug(string $msg, array $ctx = []): void { $this->log(self::DEBUG, $msg, $ctx); }
    public function info(string $msg, array $ctx = []): void  { $this->log(self::INFO, $msg, $ctx); }
    public function warn(string $msg, array $ctx = []): void  { $this->log(self::WARN, $msg, $ctx); }
    public function error(string $msg, array $ctx = []): void { $this->log(self::ERROR, $msg, $ctx); }

    private function log(int $level, string $msg, array $ctx): void
    {
        if ($level < $this->minLevel) return;

        $line = sprintf("[%s] %s: %s %s\n",
            date('Y-m-d H:i:s'),
            self::$levels[$level],
            $msg,
            $ctx !== [] ? json_encode($ctx, JSON_UNESCAPED_SLASHES) : '',
        );
        @file_put_contents($this->file, $line, FILE_APPEND | LOCK_EX);
    }
}
