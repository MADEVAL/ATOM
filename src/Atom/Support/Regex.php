<?php
declare(strict_types=1);
namespace Atom\Support;

final readonly class Regex
{
    /** @param int-mask<PREG_OFFSET_CAPTURE, PREG_UNMATCHED_AS_NULL> $flags */
    public static function match(string $pattern, string $subject, int $flags = 0): ?array
    {
        $r = @preg_match($pattern, $subject, $m, $flags);
        if ($r === false) throw new \RuntimeException('PCRE error: ' . preg_last_error_msg());
        return $r === 1 ? $m : null;
    }

    /** @param int-mask<PREG_PATTERN_ORDER, PREG_SET_ORDER, PREG_OFFSET_CAPTURE, PREG_UNMATCHED_AS_NULL> $flags */
    public static function matchAll(string $pattern, string $subject, int $flags = 0): array
    {
        $r = @preg_match_all($pattern, $subject, $m, $flags);
        if ($r === false) throw new \RuntimeException('PCRE error: ' . preg_last_error_msg());
        return $m;
    }

    public static function replace(string|array $pattern, string|array|callable $replace, string $subject): string
    {
        $r = is_callable($replace)
            ? preg_replace_callback($pattern, $replace, $subject)
            : preg_replace($pattern, $replace, $subject);
        return $r ?? throw new \RuntimeException('PCRE error: ' . preg_last_error_msg());
    }

    /** @param int-mask<PREG_SPLIT_NO_EMPTY, PREG_SPLIT_DELIM_CAPTURE, PREG_SPLIT_OFFSET_CAPTURE> $flags */
    public static function split(string $pattern, string $subject, int $flags = PREG_SPLIT_NO_EMPTY): array
    {
        $r = preg_split($pattern, $subject, -1, $flags);
        if ($r === false) throw new \RuntimeException('PCRE error: ' . preg_last_error_msg());
        return $r;
    }

    public static function quote(string $str): string
    {
        return preg_quote($str, '#');
    }

    public static function assert(string $pattern): void
    {
        if (@preg_match($pattern, '') === false) {
            throw new \InvalidArgumentException("Bad regex: {$pattern} - " . preg_last_error_msg());
        }
    }
}
