# Changelog

All notable changes to Atom will be documented in this file.

## [0.0.3] - 2026-05-27

### Added
- **WebSocket server** — first-class RFC 6455 support: frame encode/decode, non-blocking event loop, rooms, broadcasting, ping/pong, close handshake (~250 LOC across `Connection` + `Server`)
- **`$app->ws($path, $handler)`** — fluent WebSocket route registration API
- **`php atom ws:serve`** — CLI command to start WebSocket server
- **`Response::getHeader()`** — accessor for testing response headers
- **PHPStan** configuration (`phpstan.neon`) at level `max`
- **CHANGELOG.md** and **CONTRIBUTING.md**
- **Docs split** into 10 pages + `style.css`; added `websocket.html`

### Security
- **Session::csrfToken()** — removed weak `uniqid()` fallback; now uses `random_bytes()` exclusively for cryptographically secure CSRF tokens
- **Request::method** — method spoofing now validates against a whitelist of allowed HTTP methods (GET, POST, PUT, PATCH, DELETE, OPTIONS, HEAD); arbitrary values are rejected

### Added
- **Response::send()** — added built-in security headers: `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy: strict-origin-when-cross-origin`
- **Logger::log()** — added exclusive file lock (`flock`) around size-check → rotate → write cycle to prevent race conditions in concurrent environments
- **PHPStan** configuration (`phpstan.neon`) at level `max`
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
