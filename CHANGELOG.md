# Changelog

All notable changes to `liquidafy/liquidafy-php` are documented here.
This project follows [Semantic Versioning](https://semver.org/) and the
[Keep a Changelog](https://keepachangelog.com/) format.

## [1.0.0] - 2026-06-11

First stable release. Targets Liquidafy API version `2026-05-01`.

### Added

- `Liquidafy\Client` — single entry point; options: `base_url`, `timeout`,
  `max_retries`, `retry_initial_delay_ms`, `telemetry`, `api_version`.
- Resources:
  - `charges` — `create`, `get`, `list`, `cancel`, `refund`
  - `withdrawals` — `create` (crypto + fiat), `get`, `list`, `cancel`
  - `refunds` — `create` (via charge), `get`, `list`
  - `webhookEndpoints` — `create`, `get`, `list`, `update`, `delete`,
    `rotateSecret`, `disable`, `enable`, `test`, `deliveries`
  - `fx` — `rates`, `createQuote`/`quote`, `getQuote`
  - `merchants` — `get('me')`, `update`
- `Liquidafy\Webhook::constructEvent()` — `Liquidafy-Signature`
  (`t=<unix>,v1=<hex hmac-sha256>`) verification with constant-time
  comparison (`hash_equals`) and 300s replay-protection tolerance.
- Automatic retries (3x, exponential backoff 1s/2s/4s + jitter) on
  network errors, 5xx, and 429 honoring `Retry-After` — never on other 4xx.
- `Idempotency-Key` injection via `$opts['idempotency_key']` on every
  mutating call.
- Cursor pagination with lazy `->autoPaging()` iterator.
- Typed exceptions: `LiquidafyAPIError` (base, with `code`/`type`/
  `request_id`/HTTP status), `AuthenticationException`,
  `InvalidRequestException`, `RateLimitException`,
  `ApiConnectionException`, `SignatureVerificationException`.
- API keys are always masked (`lr_live_***...123`) in error messages and
  never logged.
- Sandbox auto-detection by key prefix (`lr_test_`).
- Telemetry `User-Agent` (`Liquidafy-PHP/1.0.0 (PHP/x.y.z; OS)`) and
  `setAppInfo()` for integrator identification.
