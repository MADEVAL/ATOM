<?php
declare(strict_types=1);
namespace Atom\Support;

use Atom\Constants;

final class Logger
{
    public const DEBUG     = 0;
    public const INFO      = 1;
    public const WARN      = 2;
    public const ERROR     = 3;
    public const CRITICAL  = 4;
    public const ALERT     = 5;
    public const EMERGENCY = 6;

    private static array $levels = ['DEBUG', 'INFO', 'WARN', 'ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'];

    public function __construct(
        private string $file,
        private int $minLevel = self::DEBUG,
        private int $maxSize = 0,
    ) {}

    public function debug(string $msg, array $ctx = []): void     { $this->log(self::DEBUG, $msg, $ctx); }
    public function info(string $msg, array $ctx = []): void      { $this->log(self::INFO, $msg, $ctx); }
    public function warn(string $msg, array $ctx = []): void      { $this->log(self::WARN, $msg, $ctx); }
    public function error(string $msg, array $ctx = []): void     { $this->log(self::ERROR, $msg, $ctx); }
    public function critical(string $msg, array $ctx = []): void  { $this->log(self::CRITICAL, $msg, $ctx); }
    public function alert(string $msg, array $ctx = []): void     { $this->log(self::ALERT, $msg, $ctx); }
    public function emergency(string $msg, array $ctx = []): void { $this->log(self::EMERGENCY, $msg, $ctx); }

    public function clear(): bool
    {
        if (is_file($this->file)) {
            return unlink($this->file);
        }
        return true;
    }

    public function rotate(): void
    {
        if (!is_file($this->file)) return;
        $info = pathinfo($this->file);
        $rotated = $info['dirname'] . '/' . $info['filename'] . '_' . date('Ymd_His') . '.' . ($info['extension'] ?? 'log');
        rename($this->file, $rotated);
    }

    /** @param array<string,mixed> $ctx */
    private function log(int $level, string $msg, array $ctx): void
    {
        if ($level < $this->minLevel) return;
        if (!isset(self::$levels[$level])) {
            $level = self::ERROR;
        }

        $dir = dirname($this->file);
        if (!is_dir($dir) && !@mkdir($dir, Constants::DIR_PERMISSIONS, true) && !is_dir($dir)) {
            throw new \RuntimeException("Logger: cannot create directory '{$dir}'");
        }

        $ctxStr = '';
        if ($ctx !== []) {
            try {
                $ctxStr = json_encode($ctx, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                $ctxStr = json_encode(['error' => 'Context serialization failed']);
            }
        }

        $line = sprintf("[%s] %s: %s %s\n",
            date('Y-m-d H:i:s'),
            self::$levels[$level],
            $msg,
            $ctxStr,
        );

        $fp = @fopen($this->file, 'c+');
        if ($fp === false) {
            throw new \RuntimeException("Logger: failed to open '{$this->file}'");
        }
        if (flock($fp, LOCK_EX)) {
            clearstatcache(true, $this->file);
            if ($this->maxSize > 0 && is_file($this->file) && filesize($this->file) >= $this->maxSize) {
                flock($fp, LOCK_UN);
                fclose($fp);
                $this->rotate();
                $fp = @fopen($this->file, 'c+');
                if ($fp === false) {
                    throw new \RuntimeException("Logger: failed to open '{$this->file}' after rotation");
                }
                flock($fp, LOCK_EX);
            }
            fseek($fp, 0, SEEK_END);
            if (fwrite($fp, $line) === false) {
                throw new \RuntimeException("Logger: failed to write to '{$this->file}'");
            }
            flock($fp, LOCK_UN);
        } else {
            fclose($fp);
            throw new \RuntimeException("Logger: failed to acquire lock on '{$this->file}'");
        }
        fclose($fp);
    }
}
