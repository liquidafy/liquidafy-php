# Contributing to liquidafy-php

PRs are welcome! This is a company-maintained project (Liquidafy Labs Ltda), so final API decisions stay with the maintainers — but bug fixes, docs and test improvements are always appreciated.

> **Security issue?** Do NOT open a PR or issue — see [SECURITY.md](SECURITY.md).

## Development setup

Requirements: PHP 8.1+ and Composer 2 — or just Docker.

### With local PHP

```bash
composer install
composer test   # PHPUnit
composer stan   # PHPStan level 8
```

### With Docker (no local PHP needed)

Run from the repo root:

```bash
docker run --rm -v "$PWD:/app" -w /app composer:2 composer install
docker run --rm -v "$PWD:/app" -w /app composer:2 composer test
docker run --rm -v "$PWD:/app" -w /app composer:2 composer stan
```

## Code style & quality bar

- **PHPStan level 8** must pass with zero errors (`composer stan`).
- PSR-12-ish formatting, PSR-4 autoloading (`Liquidafy\` → `src/`).
- Every behaviour change needs a PHPUnit test next to the existing ones in `tests/`.
- Money values are **decimal strings**, never floats.
- Secrets (API keys, webhook secrets) must never appear in exception messages, logs or test fixtures — only the masked form (`lr_live_***...123`).
- Webhook signature comparison must remain constant-time (`hash_equals`).

## Submitting a pull request

1. Fork and create a branch from `main`.
2. Make your change + tests.
3. Make sure `composer validate --strict`, `composer stan` and `composer test` pass locally.
4. Open a PR against `main` with a clear description of what changes and why.
5. CI (PHP 8.1/8.2/8.3 matrix) must be green before review/merge.

By submitting a PR you agree that your contribution is licensed under the [MIT License](LICENSE) of this project.

## Questions

- Bugs / features: [GitHub issues](https://github.com/liquidafy/liquidafy-php/issues)
- General support: support@liquidafy.com
