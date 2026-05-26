<?php
declare(strict_types=1);
namespace Atom\Support;

final readonly class Regex
{
    public static function match(string $pattern, string $subject, int $flags = 0): ?array
    {
        return preg_match($pattern, $subject, $m, $flags) === 1 ? $m : null;
    }

    public static function matchAll(string $pattern, string $subject, int $flags = 0): array
    {
        preg_match_all($pattern, $subject, $m, $flags);
        return $m;
    }

    public static function replace(string|array $pattern, string|array|callable $replace, string $subject): string
    {
        $r = is_callable($replace)
            ? preg_replace_callback($pattern, $replace, $subject)
            : preg_replace($pattern, $replace, $subject);
        return $r ?? throw new \RuntimeException('PCRE error: ' . preg_last_error_msg());
    }

    public static function split(string $pattern, string $subject): array
    {
        return preg_split($pattern, $subject, -1, PREG_SPLIT_NO_EMPTY) ?: [];
    }

    public static function quote(string $str): string
    {
        return preg_quote($str, '#');
    }

    public static function assert(string $pattern): void
    {
        if (@preg_match($pattern, '') === false) {
            throw new \InvalidArgumentException("Bad regex: {$pattern} — " . preg_last_error_msg());
        }
    }
}
