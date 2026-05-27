<?php
declare(strict_types=1);
namespace Atom;

final readonly class Constants
{
    private function __construct() {}

    public const DIR_PERMISSIONS = 0755;

    public const COOKIE_TTL_DEFAULT = 3600;

    public const CORS_MAX_AGE = 86400;

    public const PAGINATOR_MAX_PER_PAGE = 100;

    public const PAGINATOR_DEFAULT_PER_PAGE = 20;

    public const RATELIMIT_DEFAULT_MAX = 60;

    public const RATELIMIT_DEFAULT_WINDOW = 60;

    public const RATELIMIT_CLEANUP_THRESHOLD = 10000;

    public const ENCRYPT_IV_BYTES = 12;

    public const ENCRYPT_GCM_TAG_BYTES = 16;

    public const ENCRYPT_MIN_PAYLOAD = self::ENCRYPT_IV_BYTES + self::ENCRYPT_GCM_TAG_BYTES;

    public const CSRF_RANDOM_BYTES = 32;

    public const JSON_BODY_MAX_FALLBACK = 8_388_608;

    public const SESSION_COOKIE_TTL_DEFAULT = 3600;
}
