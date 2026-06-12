<?php

declare(strict_types=1);

namespace Liquidafy\Resources;

use Liquidafy\Util\LiquidafyObject;

/**
 * Merchant profile — `/v1/me`.
 *
 * The API only exposes the merchant bound to the API key, addressed
 * as `'me'`.
 */
final class Merchants extends AbstractResource
{
    /**
     * Read the active merchant's profile snapshot. `GET /v1/me`
     *
     * Only `'me'` is supported — the API key is already scoped to a
     * single merchant.
     */
    public function get(string $id = 'me'): LiquidafyObject
    {
        if ($id !== 'me') {
            throw new \InvalidArgumentException(
                "Only 'me' is supported — the API key is scoped to a single merchant."
            );
        }

        return $this->requestObject('GET', '/v1/me');
    }

    /**
     * Update merchant name / display name. `PATCH /v1/me`
     *
     * @param array<string, mixed> $params `name`, `display_name`
     * @param array<string, mixed> $opts   supports `idempotency_key`
     */
    public function update(array $params, array $opts = []): LiquidafyObject
    {
        return $this->requestObject('PATCH', '/v1/me', [], $params, $opts);
    }
}
