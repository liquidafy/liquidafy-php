<?php

declare(strict_types=1);

namespace Liquidafy\Resources;

use Liquidafy\Util\Collection;
use Liquidafy\Util\LiquidafyObject;

/**
 * Withdrawals — `/v1/withdrawals` (crypto and fiat).
 */
final class Withdrawals extends AbstractResource
{
    /**
     * Create a withdrawal. `POST /v1/withdrawals`
     *
     * Crypto: `type=crypto`, `amount`, `currency`, `chain`,
     * `to_address` (+ optional `step_up_challenge_id`, `travel_rule`,
     * `metadata`).
     *
     * Fiat: `type=fiat`, `amount_usdt`, `target_currency`, `quote_id`,
     * `destination{kind,value,extra}` (+ optional
     * `step_up_challenge_id`, `metadata`).
     *
     * Amounts are decimal strings — never floats.
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $opts  supports `idempotency_key`
     */
    public function create(array $params, array $opts = []): LiquidafyObject
    {
        $this->assertMoneyStrings($params, ['amount', 'amount_usdt']);

        return $this->requestObject('POST', '/v1/withdrawals', [], $params, $opts);
    }

    /**
     * Retrieve a withdrawal. `GET /v1/withdrawals/{id}`
     */
    public function get(string $id): LiquidafyObject
    {
        return $this->requestObject('GET', '/v1/withdrawals/' . $this->pathId($id));
    }

    /**
     * List withdrawals. `GET /v1/withdrawals`
     *
     * Filters: `type` (crypto|fiat), `status`, `cursor`, `limit`.
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $opts
     */
    public function list(array $params = [], array $opts = []): Collection
    {
        return $this->requestCollection('/v1/withdrawals', $params, $opts);
    }

    /**
     * Cancel a withdrawal that has not dispatched yet.
     * `POST /v1/withdrawals/{id}/cancel`
     *
     * Only `pending_signing` (crypto) and `pending_dispatch` (fiat)
     * are cancellable.
     *
     * @param array<string, mixed> $params optional `reason`
     * @param array<string, mixed> $opts
     */
    public function cancel(string $id, array $params = [], array $opts = []): LiquidafyObject
    {
        return $this->requestObject('POST', '/v1/withdrawals/' . $this->pathId($id) . '/cancel', [], $params, $opts);
    }
}
