<?php

declare(strict_types=1);

namespace Liquidafy\Resources;

use Liquidafy\Util\LiquidafyObject;

/**
 * FX — `/v1/fx/*`.
 *
 * Indicative rates plus 60-second locked quotes used by fiat
 * withdrawals.
 */
final class Fx extends AbstractResource
{
    /**
     * Indicative rate snapshot. `GET /v1/fx/rates`
     *
     * Response: `{rates: [{from, to, rate, provider, updated_at}],
     * disclaimer}` — rates are informational only; settlement uses a
     * locked quote.
     *
     * @param array<string, mixed> $params optional `from`, `to` filters
     * @param array<string, mixed> $opts
     */
    public function rates(array $params = [], array $opts = []): LiquidafyObject
    {
        return $this->requestObject('GET', '/v1/fx/rates', $params, null, $opts);
    }

    /**
     * Create an FX quote (locked for 60s). `POST /v1/fx/quote`
     *
     * Required: `from` (e.g. "USDT"), `to` (e.g. "BRL"), `amount`
     * (decimal string). Optional: `side` (`sell`|`buy`, default sell).
     *
     * Response fields: `id`, `object=fx_quote`, `from`, `to`, `side`,
     * `amount_usdt`, `rate`, `fee_usdt`, `fee_percent_bps`,
     * `fee_fixed_usdt`, `net_usdt`, `net_target`, `provider`,
     * `expires_at`, `livemode`, `created_at`.
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $opts
     */
    public function createQuote(array $params, array $opts = []): LiquidafyObject
    {
        $this->assertMoneyStrings($params, ['amount']);

        return $this->requestObject('POST', '/v1/fx/quote', [], $params, $opts);
    }

    /**
     * Alias of {@see createQuote()} — `$liquidafy->fx->quote([...])`.
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $opts
     */
    public function quote(array $params, array $opts = []): LiquidafyObject
    {
        return $this->createQuote($params, $opts);
    }

    /**
     * Retrieve a quote before consuming it. `GET /v1/fx/quote/{id}`
     *
     * 410 (Gone) after the 60s TTL.
     */
    public function getQuote(string $id): LiquidafyObject
    {
        return $this->requestObject('GET', '/v1/fx/quote/' . $this->pathId($id));
    }
}
