<?php

declare(strict_types=1);

namespace Liquidafy;

use Liquidafy\HTTP\HttpClient;
use Liquidafy\Resources\Charges;
use Liquidafy\Resources\Fx;
use Liquidafy\Resources\Merchants;
use Liquidafy\Resources\PaymentLinks;
use Liquidafy\Resources\Refunds;
use Liquidafy\Resources\WebhookEndpoints;
use Liquidafy\Resources\Withdrawals;
use Liquidafy\Util\Secret;

/**
 * Liquidafy API client.
 *
 * ```php
 * $liquidafy = new \Liquidafy\Client('lr_live_...');
 *
 * $charge = $liquidafy->charges->create([
 *     'amount'   => '100.00',   // money is ALWAYS a decimal string
 *     'currency' => 'USDT',
 * ], ['idempotency_key' => 'order-1234']);
 *
 * echo $charge->checkout_url;
 * ```
 *
 * Sandbox is selected by the key prefix: `lr_test_...` keys run in
 * test mode (`livemode=false` server-side); `lr_live_...` keys are
 * production. Keep keys in environment configuration — never commit
 * them, never use a live key in a browser context.
 */
final class Client
{
    public const VERSION = HttpClient::SDK_VERSION;
    public const API_VERSION = HttpClient::DEFAULT_API_VERSION;
    public const DEFAULT_BASE_URL = HttpClient::DEFAULT_BASE_URL;

    public const LIVE_KEY_PREFIX = 'lr_live_';
    public const TEST_KEY_PREFIX = 'lr_test_';

    public readonly Charges $charges;
    public readonly PaymentLinks $paymentLinks;
    public readonly Withdrawals $withdrawals;
    public readonly Refunds $refunds;
    public readonly WebhookEndpoints $webhookEndpoints;
    public readonly Fx $fx;
    public readonly Merchants $merchants;

    private readonly HttpClient $http;
    private readonly string $apiKey;

    /**
     * @param string               $apiKey  `lr_live_...` or `lr_test_...`
     * @param array<string, mixed> $options
     *                                      - `base_url` (string)  API origin; default https://api.liquidafy.com
     *                                      - `timeout` (float)    per-request timeout in seconds; default 30
     *                                      - `max_retries` (int)  automatic retries on network/5xx/429; default 3
     *                                      - `retry_initial_delay_ms` (int) first backoff step; default 1000 (1s, 2s, 4s, ...)
     *                                      - `telemetry` (bool)   include PHP/OS info in User-Agent; default true
     *                                      - `api_version` (string) `Liquidafy-API-Version` pin; default 2026-05-01
     *                                      - `http_client` (\GuzzleHttp\ClientInterface) injectable transport (tests)
     */
    public function __construct(string $apiKey, array $options = [])
    {
        $apiKey = trim($apiKey);
        if ($apiKey === '') {
            throw new \InvalidArgumentException('Liquidafy API key must not be empty.');
        }
        if (!str_starts_with($apiKey, self::LIVE_KEY_PREFIX) && !str_starts_with($apiKey, self::TEST_KEY_PREFIX)) {
            throw new \InvalidArgumentException(
                sprintf(
                    "Invalid Liquidafy API key '%s' — expected a key starting with '%s' or '%s'.",
                    Secret::mask($apiKey),
                    self::LIVE_KEY_PREFIX,
                    self::TEST_KEY_PREFIX,
                )
            );
        }

        $this->apiKey = $apiKey;
        $this->http = new HttpClient($apiKey, $options);

        $this->charges = new Charges($this->http);
        $this->paymentLinks = new PaymentLinks($this->http);
        $this->withdrawals = new Withdrawals($this->http);
        $this->refunds = new Refunds($this->http);
        $this->webhookEndpoints = new WebhookEndpoints($this->http);
        $this->fx = new Fx($this->http);
        $this->merchants = new Merchants($this->http);
    }

    /** True when the key is a `lr_test_` sandbox key. */
    public function isSandbox(): bool
    {
        return str_starts_with($this->apiKey, self::TEST_KEY_PREFIX);
    }

    /** True when the key is a `lr_live_` production key. */
    public function isLivemode(): bool
    {
        return !$this->isSandbox();
    }

    /**
     * Identifies your application in the `User-Agent`:
     * `Liquidafy-PHP/1.0.0 MeuApp/1.2.3 (https://meuapp.com) (PHP/8.1.5; Linux)`
     */
    public function setAppInfo(string $name, ?string $version = null, ?string $url = null): void
    {
        $this->http->setAppInfo($name, $version, $url);
    }

    public function baseUrl(): string
    {
        return $this->http->baseUrl();
    }

    /**
     * Underlying transport — exposed for advanced usage and tests.
     */
    public function httpClient(): HttpClient
    {
        return $this->http;
    }
}
