<?php

declare(strict_types=1);

namespace Liquidafy\Resources;

use Liquidafy\Util\Collection;
use Liquidafy\Util\LiquidafyObject;

/**
 * PaymentLinks — `/v1/payment_links`.
 *
 * A reusable, no-code "get paid" link. Each time a buyer opens it
 * (`pay.liquidafy.com/l/{slug}`) it mints a fresh charge, so one link can
 * collect many payments. Fixed-amount or buyer-chooses (`mode`), with an
 * optional usage cap and expiry.
 */
final class PaymentLinks extends AbstractResource
{
    /**
     * Create a payment link. `POST /v1/payment_links`
     *
     * Common fields: `title`, `mode` (fixed | flexible), `amount`
     * (decimal string, for fixed), `min_amount`/`max_amount` (for
     * flexible), `description`, `collect_buyer`, `redirect_url`,
     * `cancel_url`, `max_uses`, `expires_at`, `metadata`.
     *
     * Pass `$opts['idempotency_key']` to make the call safely retryable.
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $opts
     */
    public function create(array $params, array $opts = []): LiquidafyObject
    {
        $this->assertMoneyStrings($params, ['amount', 'min_amount', 'max_amount']);

        return $this->requestObject('POST', '/v1/payment_links', [], $params, $opts);
    }

    /**
     * Retrieve a payment link. `GET /v1/payment_links/{id}`
     */
    public function get(string $id): LiquidafyObject
    {
        return $this->requestObject('GET', '/v1/payment_links/' . $this->pathId($id));
    }

    /**
     * List payment links. `GET /v1/payment_links`
     *
     * Filters: `active`, `cursor`, `limit`.
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $opts
     */
    public function list(array $params = [], array $opts = []): Collection
    {
        return $this->requestCollection('/v1/payment_links', $params, $opts);
    }

    /**
     * Update a payment link. `PATCH /v1/payment_links/{id}`
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $opts
     */
    public function update(string $id, array $params, array $opts = []): LiquidafyObject
    {
        $this->assertMoneyStrings($params, ['amount', 'min_amount', 'max_amount']);

        return $this->requestObject('PATCH', '/v1/payment_links/' . $this->pathId($id), [], $params, $opts);
    }

    /**
     * Deactivate a payment link. `POST /v1/payment_links/{id}/deactivate`
     *
     * @param array<string, mixed> $opts
     */
    public function deactivate(string $id, array $opts = []): LiquidafyObject
    {
        return $this->requestObject('POST', '/v1/payment_links/' . $this->pathId($id) . '/deactivate', [], [], $opts);
    }

    /**
     * List the charges minted by a payment link.
     * `GET /v1/payment_links/{id}/charges`
     *
     * Filters: `cursor`, `limit`.
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $opts
     */
    public function charges(string $id, array $params = [], array $opts = []): Collection
    {
        return $this->requestCollection('/v1/payment_links/' . $this->pathId($id) . '/charges', $params, $opts);
    }
}
