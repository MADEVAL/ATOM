# Contributing to Atom

Thanks for your interest in contributing to Atom! This document outlines the process and expectations.

## Development Setup

```bash
git clone https://github.com/php-atom/framework.git
cd framework
composer install
```

## Requirements

- PHP 8.5+
- Composer

## Running Tests

```bash
composer test             # all tests
composer test-coverage    # with coverage report
```

## Static Analysis

```bash
vendor/bin/phpstan analyse
```

## Code Style

- `declare(strict_types=1)` in every PHP file
- All classes `final` (or `readonly final`) unless designed for extension (`abstract Template`)
- Immutable objects where possible (clone-on-write pattern)
- PSR-4 autoloading: namespace `Atom\` maps to `src/Atom/`
- One class per file
- PHP 8.5 features preferred (property hooks, asymmetric visibility, named arguments, enums)
- No runtime dependencies — the framework must remain zero-dependency

## Pull Request Process

1. Fork the repository
2. Create a feature branch
3. Write tests for new functionality
4. Ensure all tests pass (`composer test`)
5. Ensure static analysis passes (`vendor/bin/phpstan analyse`)
6. Update documentation if needed
7. Submit a pull request

## Security

If you discover a security vulnerability, please do NOT open a public issue. Contact the maintainers directly.

## License

By contributing, you agree that your contributions will be licensed under the GPL-3.0-or-later license.
