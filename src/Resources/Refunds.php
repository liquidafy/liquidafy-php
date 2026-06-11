<?php

declare(strict_types=1);

namespace Liquidafy\Resources;

use Liquidafy\Util\Collection;
use Liquidafy\Util\LiquidafyObject;

/**
 * Refunds — withdrawals with `kind=refund`.
 *
 * Creation happens on the charge (`POST /v1/charges/{id}/refund`);
 * reads live under `/v1/refunds`.
 */
final class Refunds extends AbstractResource
{
    /**
     * Refund a paid charge. `POST /v1/charges/{charge_id}/refund`
     *
     * Convenience alias of `$client->charges->refund()`. Required:
     * `to_address` (refund-to-source, ADR-0030) and
     * `step_up_challenge_id`; optional `amount` (decimal string,
     * defaults to full paid amount) and `metadata`.
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $opts  supports `idempotency_key`
     */
    public function create(string $chargeId, array $params, array $opts = []): LiquidafyObject
    {
        $this->assertMoneyStrings($params, ['amount']);

        return $this->requestObject('POST', '/v1/charges/' . $this->pathId($chargeId) . '/refund', [], $params, $opts);
    }

    /**
     * Retrieve a refund. `GET /v1/refunds/{id}`
     */
    public function get(string $id): LiquidafyObject
    {
        return $this->requestObject('GET', '/v1/refunds/' . $this->pathId($id));
    }

    /**
     * List refunds for the active merchant. `GET /v1/refunds`
     *
     * Filters: `status`, `cursor`, `limit`.
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $opts
     */
    public function list(array $params = [], array $opts = []): Collection
    {
        return $this->requestCollection('/v1/refunds', $params, $opts);
    }
}
