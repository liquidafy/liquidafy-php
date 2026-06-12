<?php

declare(strict_types=1);

namespace Liquidafy\Resources;

use Liquidafy\Util\Collection;
use Liquidafy\Util\LiquidafyObject;

/**
 * Webhook endpoints — `/v1/webhook_endpoints`.
 */
final class WebhookEndpoints extends AbstractResource
{
    /**
     * Register a webhook endpoint. `POST /v1/webhook_endpoints`
     *
     * Required: `url` (HTTPS, public host). Optional: `description`,
     * `events` (e.g. `["charge.*","withdrawal.*"]`), `api_version`,
     * `metadata`.
     *
     * ⚠️ The signing `secret` is returned ONLY on this call (and on
     * rotate) — store it immediately.
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $opts  supports `idempotency_key`
     */
    public function create(array $params, array $opts = []): LiquidafyObject
    {
        return $this->requestObject('POST', '/v1/webhook_endpoints', [], $params, $opts);
    }

    /**
     * Retrieve an endpoint. `GET /v1/webhook_endpoints/{id}`
     */
    public function get(string $id): LiquidafyObject
    {
        return $this->requestObject('GET', '/v1/webhook_endpoints/' . $this->pathId($id));
    }

    /**
     * List endpoints. `GET /v1/webhook_endpoints`
     *
     * @param array<string, mixed> $params `cursor`, `limit`
     * @param array<string, mixed> $opts
     */
    public function list(array $params = [], array $opts = []): Collection
    {
        return $this->requestCollection('/v1/webhook_endpoints', $params, $opts);
    }

    /**
     * Update url / description / events / metadata.
     * `PATCH /v1/webhook_endpoints/{id}`
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $opts
     */
    public function update(string $id, array $params, array $opts = []): LiquidafyObject
    {
        return $this->requestObject('PATCH', '/v1/webhook_endpoints/' . $this->pathId($id), [], $params, $opts);
    }

    /**
     * Delete an endpoint. `DELETE /v1/webhook_endpoints/{id}` (204)
     */
    public function delete(string $id): void
    {
        $this->http->request('DELETE', '/v1/webhook_endpoints/' . $this->pathId($id));
    }

    /**
     * Rotate the signing secret (previous stays valid for 24h).
     * `POST /v1/webhook_endpoints/{id}/rotate`
     *
     * Response: `{secret, previous_expires_at}` — the new secret is
     * returned ONCE.
     *
     * @param array<string, mixed> $opts
     */
    public function rotateSecret(string $id, array $opts = []): LiquidafyObject
    {
        return $this->requestObject('POST', '/v1/webhook_endpoints/' . $this->pathId($id) . '/rotate', [], [], $opts);
    }

    /**
     * Pause deliveries. `POST /v1/webhook_endpoints/{id}/disable`
     *
     * @param array<string, mixed> $opts
     */
    public function disable(string $id, array $opts = []): LiquidafyObject
    {
        return $this->requestObject('POST', '/v1/webhook_endpoints/' . $this->pathId($id) . '/disable', [], [], $opts);
    }

    /**
     * Re-enable a disabled endpoint.
     * `POST /v1/webhook_endpoints/{id}/enable`
     *
     * @param array<string, mixed> $opts
     */
    public function enable(string $id, array $opts = []): LiquidafyObject
    {
        return $this->requestObject('POST', '/v1/webhook_endpoints/' . $this->pathId($id) . '/enable', [], [], $opts);
    }

    /**
     * Fire a synchronous `test.ping` at the endpoint.
     * `POST /v1/webhook_endpoints/{id}/test`
     *
     * A failed delivery is reported as `delivered: false` — it is NOT
     * an exception. Rate limited to 6/min.
     *
     * @param array<string, mixed> $opts
     */
    public function test(string $id, array $opts = []): LiquidafyObject
    {
        return $this->requestObject('POST', '/v1/webhook_endpoints/' . $this->pathId($id) . '/test', [], [], $opts);
    }

    /**
     * Delivery attempt history, newest first.
     * `GET /v1/webhook_endpoints/{id}/deliveries`
     *
     * Filters: `status`, `before` (RFC3339 cursor), `limit`.
     * Response shape: `{deliveries: [...]}`.
     *
     * @param array<string, mixed> $params
     * @param array<string, mixed> $opts
     */
    public function deliveries(string $id, array $params = [], array $opts = []): LiquidafyObject
    {
        return $this->requestObject('GET', '/v1/webhook_endpoints/' . $this->pathId($id) . '/deliveries', $params, null, $opts);
    }
}
