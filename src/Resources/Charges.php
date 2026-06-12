<?php

declare(strict_types=1);

namespace Liquidafy\Resources;

use Liquidafy\Util\Collection;
use Liquidafy\Util\LiquidafyObject;

/**
 * Charges — `/v1/charges`.
 *
 * A charge has a unique deposit address; the buyer pays on-chain and
 * Liquidafy confirms it (`pending → partial → confirmed/...`).
 */
final class Charges extends AbstractResource
{
    /**
     * Create a charge. `POST /v1/charges`
     *
     * Required: `amount` (decimal string), `currency`.
     * Optional: `chain`, `expires_in_seconds`, `description`,
     * `customer{email,name}`, `redirect_url`, `cancel_url`, `metadata`.
     *
     * Pass `$opts['idempotency_key']` to make the call safely
     * retryable end-to-end.
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $opts
     */
    public function create(array $params, array $opts = []): LiquidafyObject
    {
        $this->assertMoneyStrings($params, ['amount']);

        return $this->requestObject('POST', '/v1/charges', [], $params, $opts);
    }

    /**
     * Retrieve a charge. `GET /v1/charges/{id}`
     */
    public function get(string $id): LiquidafyObject
    {
        return $this->requestObject('GET', '/v1/charges/' . $this->pathId($id));
    }

    /**
     * List charges. `GET /v1/charges`
     *
     * Filters: `status`, `created_after`, `created_before`,
     * `customer_email`, `cursor`, `limit`.
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $opts
     */
    public function list(array $params = [], array $opts = []): Collection
    {
        return $this->requestCollection('/v1/charges', $params, $opts);
    }

    /**
     * Cancel a charge. `POST /v1/charges/{id}/cancel`
     *
     * @param array<string, mixed> $opts
     */
    public function cancel(string $id, array $opts = []): LiquidafyObject
    {
        return $this->requestObject('POST', '/v1/charges/' . $this->pathId($id) . '/cancel', [], [], $opts);
    }

    /**
     * Refund a paid charge (partial or full).
     * `POST /v1/charges/{id}/refund`
     *
     * Required: `to_address` (refund-to-source — must be the
     * `from_address` of a CONFIRMED deposit of this charge)
     * and `step_up_challenge_id`. Optional: `amount` (decimal string;
     * defaults to the full paid amount), `metadata`.
     *
     * Returns a Withdrawal with `kind=refund`.
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $opts
     */
    public function refund(string $id, array $params, array $opts = []): LiquidafyObject
    {
        $this->assertMoneyStrings($params, ['amount']);

        return $this->requestObject('POST', '/v1/charges/' . $this->pathId($id) . '/refund', [], $params, $opts);
    }
}
