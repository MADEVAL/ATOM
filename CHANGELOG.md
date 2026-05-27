# Changelog

All notable changes to Atom will be documented in this file.

## [0.0.5] - 2026-05-28

### Fixed
- **Router cache** - compiled route cache now includes a version and route signature; stale cache entries are ignored instead of dispatching old routes.
- **Templates** - `{% for %}` over null or non-iterable values now behaves as an empty loop instead of emitting warnings.
- **ORM eager loading** - relation eager loading now respects `#[Column]` property mapping for `belongsTo`, `hasOne`, and `hasMany`.
- **Request JSON parsing** - accepts both `CONTENT_TYPE` and `HTTP_CONTENT_TYPE` SAPI keys.
- **CORS** - reflected wildcard origins now include `Vary: Origin` on normal and preflight responses.
- **WebSocket** - handshake validates method, upgrade headers, version 13, and key length; frame parsing validates RSV bits, opcodes, fragmentation state, control-frame rules, close-code ranges, masking, and oversized payloads.
- **UploadedFile** - `move()` is now root-bound: `move($root, $relativePath = '')` resolves every destination inside the allowed upload root.
- **Attribute routing** - PHP class discovery now uses `token_get_all()` instead of regex parsing.

### Changed
- **Query builder** - validates SQL identifiers, whitelists operators, rejects invalid sort directions, and defines deterministic empty `whereIn` / `whereNotIn` semantics.
- **Static analysis** - added `phpstan/phpstan` as a dev dependency, `composer stan`, and a clean PHPStan level 5 configuration.
- **Documentation and opencode skills** - synced routing cache, ORM, HTTP, CORS, WebSocket, test, and coverage notes with implementation.

### Tests
- Running: **728 tests, 1108 assertions, 0 failures**
- Coverage: **85.29% lines**

## [0.0.4] - 2026-05-28

### Added
- **ORM** — full Eloquent-like ORM: `#[Table]`, `#[PrimaryKey]`, `#[Column]` attributes; `Model` with `find`, `findOrFail`, `create`, `firstOrCreate`, `destroy`, `fill`, `save`, `delete`, `toArray`, timestamps; `Query` fluent builder (`where`, `whereIn`, `orWhere`, `whereBetween`, `whereNull`, `whereNotNull`, `orderBy`, `orderByDesc`, `limit`, `offset`, `get`, `first`, `firstOrFail`, `count`, `exists`, `paginate`, magic `whereX()`); Relations (`hasMany`, `belongsTo`, `hasOne`) with lazy + eager loading; ~500 LOC
- **Cache** — multi-driver caching: `ArrayDriver` (in-memory), `FileDriver` (TTL, atomic writes, probabilistic GC). `Cache` facade: `set/get/has/delete/flush/remember/rememberForever/increment/decrement`. ~170 LOC
- **`$app->cache()`** — lazy-init Cache, driver from `APP_CACHE_DRIVER` env var
- **`$app->log()`** — returns Logger from Container
- **`Response::getHeader()`** — accessor for testing
- **Security headers** in `Response::send()`: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`
- **Config: `routeCache` + `viewCache`** — cache strategy per subsystem (`'file'` = var_export/require, `'cache'` = Cache abstraction)
- **Documentation** — `docs/cache.html`, `docs/orm.html` added; all 12 doc pages + sidebars synced with code
- **PHPStan** config
- **CHANGELOG.md**, **CONTRIBUTING.md**

### Changed
- **Router** — constructor accepts `?Cache $cache` + `string $cacheStrategy`; when `APP_ROUTE_CACHE=cache`, uses `Cache::remember()` instead of `var_export`
- **View Engine** — same cache strategy support via `APP_VIEW_CACHE`
- **Logger** — registered in Application container (was orphan)
- **All `\Atom\Foo::` inline FQN** → `use Atom\Foo` imports (18 locations across 13 files)
- **Router::getAllowedMethods()** — delegates path→regex compilation instead of duplicating
- **ORM Model** — reflection metadata cached per-class (1 `ReflectionClass` instead of 11 per operation)
- **Paginator::make($page, $perPage)** — new static factory for non-Request pagination
- **Query::paginate()** — accepts `?Request`, no longer reads `$_GET` directly
- **phpunit.xml** — `requireCoverageMetadata="true"`

### Removed
- **WdServer circular dependency on Application** — Server no longer imports `Atom\Application`
- **MatchedRoute** — dead class removed
- **SKILL.md invalid claims** — "no ORM", "no event loop" removed

### Fixed
- **RateLimit Retry-After** — was always 1; now calculates from oldest timestamp
- **Router::health()** — `null`/`0` no longer treated as success
- **Request::parseJsonBody()** — `post_max_size=-1` no longer blocks JSON parsing
- **Router::getAllowedMethods()** — now returns ALL methods for same-URI routes, not just first
- **Session::csrfToken()** — removed weak `uniqid()` fallback, uses only `random_bytes()`
- **Request::method** — spoofing validated against ALLOWED_METHODS whitelist
- **Validator.php** — 18 attribute classes extracted to individual files
- **`Validator::arrayToDto()`** — resolves constructor params from data before fallback

### Tests
- Running: **714 tests, 1084 assertions, 0 failures**
- New: ORM (27), Cache (23), RateLimit (2), Router (4), Request (1)

## [0.0.3] - 2026-05-27

### Added
- **WebSocket server** — first-class RFC 6455 support: frame encode/decode, non-blocking event loop, rooms, broadcasting, ping/pong, close handshake (~250 LOC)
- **`$app->ws($path, $handler)`** — fluent WebSocket route registration API
- **`php atom ws:serve`** — CLI command to start WebSocket server
- **Cache** — multi-driver caching: `ArrayDriver` (in-memory, dev), `FileDriver` (file-based, TTL, atomic writes, probabilistic cleanup). `set/get/has/delete/flush/remember/increment/decrement` — PSR-16-like API (~120 LOC)
- **`$app->cache()`** — lazy-init cache, driver from `APP_CACHE_DRIVER` env var (defaults to file if `cacheDir` set, array otherwise)
- **`Response::getHeader()`** — accessor for testing response headers

### Security
- **Session::csrfToken()** — removed weak `uniqid()` fallback; now uses `random_bytes()` exclusively for cryptographically secure CSRF tokens
- **Request::method** — method spoofing now validates against a whitelist of allowed HTTP methods (GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD); arbitrary values are rejected

### Added
- **Response::send()** — added built-in security headers: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`
- **Logger::log()** — added exclusive file lock (`flock`) around size-check → rotate → write cycle to prevent race conditions in concurrent environments
- **PHPStan** configuration (`phpstan.neon`)
- **CHANGELOG.md** and **CONTRIBUTING.md**

### Changed
- **Validator attributes** — 18 validation attribute classes extracted into individual files under `src/Atom/Validation/` (one class per file, PSR-4 compliant)
- **Validator::arrayToDto()** — now attempts to resolve constructor parameters from data array before falling back to `newInstanceWithoutConstructor()`
- **RouteCompiler::compilePath()** — replaced fragile split/implode regex with sequential placeholder-based path compilation
- **phpunit.xml** — `requireCoverageMetadata` enabled (`true`); all test classes now declare `#[CoversClass]`

### Removed
- **MatchedRoute** — dead class `src/Atom/Routing/MatchedRoute.php` removed (was unused in production code)
- **RouteTest** — removed `matched_route_stores_all_data` test (depends on removed class)

## [0.0.2] - 2026-04-15
- Quality audit fixes

## [0.0.1] - 2026-04-01
- Initial release
