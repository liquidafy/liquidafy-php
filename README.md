# Liquidafy SDK — PHP

> Hand-crafted PHP SDK for the [Liquidafy](https://liquidafy.com) API — crypto-native checkout for LATAM merchants.
> Composer: `liquidafy/liquidafy-php`. Base library for the Magento and WooCommerce plugins.

[![CI](https://github.com/liquidafy/liquidafy-php/actions/workflows/ci.yml/badge.svg)](https://github.com/liquidafy/liquidafy-php/actions/workflows/ci.yml)

## Installation

```bash
composer require liquidafy/liquidafy-php
```

## Requirements / support matrix

| | |
|---|---|
| PHP | 8.1, 8.2, 8.3 (CI-tested) |
| HTTP | Guzzle 7 (only runtime dependency) |
| Liquidafy API version | `2026-05-01` (pinned via `Liquidafy-API-Version`) |
| SDK version | 1.0.0 (SemVer, independent of the API version) |

Sandbox is selected automatically by the key prefix: `lr_test_...` runs in
test mode against your sandbox data; `lr_live_...` is production. Keep keys
in environment configuration — never commit them, never use a secret key in
a browser.

## Quick start

```php
$liquidafy = new \Liquidafy\Client(getenv('LIQUIDAFY_API_KEY')); // lr_live_... or lr_test_...
```

Optional configuration:

```php
$liquidafy = new \Liquidafy\Client(getenv('LIQUIDAFY_API_KEY'), [
    'base_url'               => 'https://api.liquidafy.com', // default
    'timeout'                => 30,    // seconds
    'max_retries'            => 3,     // network / 5xx / 429 only — never other 4xx
    'retry_initial_delay_ms' => 1000,  // backoff: 1s, 2s, 4s (+ jitter)
    'telemetry'              => true,  // PHP/OS info in User-Agent
]);
```

### 1. Create a charge (with idempotency)

```php
use Liquidafy\Exception\LiquidafyAPIError;

try {
    $charge = $liquidafy->charges->create([
        'amount'      => '100.00',          // money is ALWAYS a decimal string — never a float
        'currency'    => 'USDT',
        'description' => 'Pedido #1234',
        'metadata'    => ['order_id' => '1234'],
    ], [
        'idempotency_key' => 'order-1234',  // safe to retry end-to-end
    ]);

    echo $charge->checkout_url;             // hosted pay page
    echo $charge->deposit_address;          // unique on-chain address
} catch (LiquidafyAPIError $e) {
    // $e->getErrorCode(), $e->getHttpStatus(), $e->getRequestId()
    error_log('Liquidafy error: ' . $e->getMessage());
}
```

### 2. Verify a webhook

Always pass the **raw** request body — re-encoding the JSON breaks the HMAC.

```php
$payload   = file_get_contents('php://input');
$sigHeader = $_SERVER['HTTP_LIQUIDAFY_SIGNATURE'] ?? '';

try {
    $event = \Liquidafy\Webhook::constructEvent(
        $payload,
        $sigHeader,
        getenv('LIQUIDAFY_WEBHOOK_SECRET'),  // from webhookEndpoints->create() / rotateSecret()
        300                                  // replay tolerance in seconds (default)
    );
} catch (\Liquidafy\Exception\SignatureVerificationException $e) {
    http_response_code(400);
    exit;
}

if ($event->type === 'charge.confirmed') {
    $charge = $event->data->object;
    // mark order $charge->metadata->order_id as paid
}

http_response_code(200);
```

The signature header is `Liquidafy-Signature: t=<unix>,v1=<hex hmac-sha256>`;
verification uses constant-time comparison and a ±300s replay window.

### 3. List with auto-pagination

```php
foreach ($liquidafy->charges->list(['status' => 'confirmed'])->autoPaging() as $charge) {
    echo $charge->id, PHP_EOL;   // fetches next pages lazily via cursor
}
```

## API surface

| Resource | Methods |
|---|---|
| `$liquidafy->charges` | `create`, `get`, `list`, `cancel`, `refund` |
| `$liquidafy->withdrawals` | `create` (crypto/fiat), `get`, `list`, `cancel` |
| `$liquidafy->refunds` | `create($chargeId, …)`, `get`, `list` |
| `$liquidafy->webhookEndpoints` | `create`, `get`, `list`, `update`, `delete`, `rotateSecret`, `disable`, `enable`, `test`, `deliveries` |
| `$liquidafy->fx` | `rates`, `createQuote` / `quote`, `getQuote` |
| `$liquidafy->merchants` | `get('me')`, `update` |
| `\Liquidafy\Webhook` | `constructEvent`, `verifyHeader`, `computeSignature` |

## Errors

All API failures throw `Liquidafy\Exception\LiquidafyAPIError` (or a subclass):

| Exception | When |
|---|---|
| `AuthenticationException` | 401 / 403 — bad key or missing permission |
| `InvalidRequestException` | 400 / 404 / 409 / 422 — never retried |
| `RateLimitException` | 429 — `getRetryAfter()` exposes the suggested wait |
| `ApiConnectionException` | network failure after retries |
| `SignatureVerificationException` | webhook signature failed |

Every exception exposes `getErrorCode()`, `getErrorType()`, `getHttpStatus()`,
`getRequestId()` and `getErrorBody()`. API keys never appear in messages —
only the masked form (`lr_live_***...123`).

## Retries

The SDK automatically retries **network errors, 5xx, and 429** (honoring
`Retry-After`) with exponential backoff + jitter — 3 attempts by default.
Other 4xx are deterministic and are never retried. Combine with
`idempotency_key` so retried mutations are replay-safe server-side.

## Telemetry

Requests identify the SDK (`User-Agent: Liquidafy-PHP/1.0.0 (PHP/8.2.x; Linux)`).
Identify your app on top of it:

```php
$liquidafy->setAppInfo('MeuApp', '1.2.3', 'https://meuapp.com');
```

Disable runtime info with `'telemetry' => false`.

## Development

```bash
composer install
composer test   # PHPUnit
composer stan   # PHPStan level 8
```

## Specs

Source of truth lives in the Liquidafy docs (private):

- `docs/06-api/sdk-strategy.md` (PHP section)
- `docs/06-api/openapi.yaml` (API `2026-05-01`)

Plugins built on this SDK: `liquidafy-magento` (Wave 1.A), `liquidafy-woocommerce` (Wave 2).

## Status

- [x] Repo skeleton
- [x] CI/CD pipeline (GitHub Actions: php 8.1–8.3, composer validate, PHPStan level 8, PHPUnit)
- [x] SDK 1.0.0 implemented (charges, withdrawals, refunds, webhook endpoints, FX, merchant, webhook verify)
- [ ] Integration tests against sandbox
- [ ] Packagist release

## Contact

- Internal: see Slack `#liquidafy-php`
- External: support@liquidafy.com

## License

[MIT](LICENSE) © Liquidafy Labs Ltda

---

[Liquidafy](https://liquidafy.com) — crypto-native checkout gateway for LATAM.
