# Contributing to Atom

Thanks for helping improve Atom. This project is intentionally small, strict, and zero-runtime-dependency; contributions should preserve those properties.

## Development Setup

```bash
git clone https://github.com/MADEVAL/ATOM.git
cd ATOM
composer install
```

## Requirements

- PHP 8.5+
- Composer
- Xdebug or PCOV only when generating coverage

## Quality Gates

Run these before opening a pull request:

```bash
composer test
composer stan
composer validate --strict --no-interaction
```

For coverage:

```bash
composer test-coverage
```

Current expected baseline:

- PHPUnit: 728 tests, 1108 assertions
- Line coverage: 85.29%
- PHPStan: level 5, no errors

## Code Standards

- Use `declare(strict_types=1)` in every PHP file.
- Keep runtime dependencies at zero. Dev dependencies are allowed only for tests and analysis.
- Use PSR-4: namespace `Atom\` maps to `src/Atom/`.
- Keep one class per file.
- Prefer small final classes. Use abstract classes only for explicit extension points such as `Model` and `Template`.
- Prefer immutable or clone-on-write APIs where the existing component already uses that style.
- Validate untrusted input at framework boundaries: HTTP, routing, SQL, files, cache paths, WebSocket frames.
- Keep public API changes documented in `README.md`, `docs/`, and `.opencode/skills/atom/`.

## Testing Expectations

- Add regression tests for every bug fix.
- Add focused tests for new behavior before broad refactors.
- Keep tests deterministic: use temporary directories, in-memory SQLite, isolated cache paths, and no network calls.
- Update coverage-sensitive tests when adding public classes or methods.

## Pull Request Process

1. Fork `MADEVAL/ATOM`.
2. Create a focused feature branch.
3. Make the smallest coherent change.
4. Add or update tests.
5. Update docs and opencode skills when behavior changes.
6. Run all quality gates.
7. Submit a pull request with a short summary, risk notes, and verification output.

## Security

Do not open a public issue for a suspected vulnerability. Report it privately to the repository maintainers.

Security-sensitive areas include:

- Request parsing and uploaded files
- Response headers and redirects
- CSRF and sessions
- SQL query generation
- Template compilation and rendering
- File cache paths and atomic writes
- WebSocket handshake and frame parsing

## License

By contributing, you agree that your contributions are licensed under GPL-3.0-or-later.
