<?php

declare(strict_types=1);

namespace Liquidafy\Tests;

use PHPUnit\Framework\TestCase;

use Liquidafy\Client;
use Liquidafy\Tests\Support\HttpMock;

/**
 * Client surface: sandbox detection by key prefix, resource wiring,
 * endpoint paths.
 */
final class ClientTest extends TestCase
{
    public function testSandboxIsDetectedByKeyPrefix(): void
    {
        $sandbox = new Client('lr_test_abc123def456ghi789');
        self::assertTrue($sandbox->isSandbox());
        self::assertFalse($sandbox->isLivemode());

        $live = new Client('lr_live_abc123def456ghi789');
        self::assertFalse($live->isSandbox());
        self::assertTrue($live->isLivemode());
    }

    public function testDefaultBaseUrl(): void
    {
        $client = new Client('lr_test_abc123def456ghi789');

        self::assertSame('https://api.liquidafy.com', $client->baseUrl());
    }

    public function testBaseUrlOverrideTrimsTrailingSlash(): void
    {
        $client = new Client('lr_test_abc123def456ghi789', ['base_url' => 'https://api.sandbox.liquidafy.com/']);

        self::assertSame('https://api.sandbox.liquidafy.com', $client->baseUrl());
    }

    public function testMerchantsGetOnlySupportsMe(): void
    {
        $mock = new HttpMock([HttpMock::json(200, ['id' => 'mer_1', 'name' => 'Loja'])]);

        $merchant = $mock->client->merchants->get('me');
        self::assertSame('Loja', $merchant->name);
        self::assertSame('/v1/me', $mock->lastRequest()->getUri()->getPath());

        $this->expectException(\InvalidArgumentException::class);
        $mock->client->merchants->get('mer_other');
    }

    public function testWebhookEndpointPaths(): void
    {
        $mock = new HttpMock([
            HttpMock::json(201, ['id' => 'we_1', 'secret' => 'whsec_x']),
            HttpMock::json(200, ['secret' => 'whsec_y', 'previous_expires_at' => '2026-06-12T00:00:00Z']),
            HttpMock::json(200, ['object' => 'webhook_test', 'delivered' => true, 'duration_ms' => 42]),
        ]);

        $endpoint = $mock->client->webhookEndpoints->create(['url' => 'https://shop.example/webhooks/liquidafy', 'events' => ['charge.*']]);
        self::assertSame('whsec_x', $endpoint->secret);

        $rotated = $mock->client->webhookEndpoints->rotateSecret('we_1');
        self::assertSame('whsec_y', $rotated->secret);

        $test = $mock->client->webhookEndpoints->test('we_1');
        self::assertTrue($test->delivered);

        $paths = array_map(
            static fn ($r): string => $r->getMethod() . ' ' . $r->getUri()->getPath(),
            $mock->requests(),
        );
        self::assertSame([
            'POST /v1/webhook_endpoints',
            'POST /v1/webhook_endpoints/we_1/rotate',
            'POST /v1/webhook_endpoints/we_1/test',
        ], $paths);
    }

    public function testFxSurface(): void
    {
        $mock = new HttpMock([
            HttpMock::json(200, [
                'rates' => [['from' => 'USDT', 'to' => 'BRL', 'rate' => '5.2000000000', 'provider' => 'partner_br_tbd', 'updated_at' => '2026-06-11T00:00:00Z']],
                'disclaimer' => 'Cotação informativa.',
            ]),
            HttpMock::json(201, ['id' => 'fxq_1', 'object' => 'fx_quote', 'rate' => '5.2000000000', 'net_target' => '5171.4000']),
            HttpMock::json(200, ['id' => 'fxq_1', 'object' => 'fx_quote']),
        ]);

        $rates = $mock->client->fx->rates(['to' => 'BRL']);
        self::assertSame('5.2000000000', $rates->rates[0]->rate);
        self::assertNotNull($rates->disclaimer);

        $quote = $mock->client->fx->createQuote(['from' => 'USDT', 'to' => 'BRL', 'amount' => '1000.00', 'side' => 'sell']);
        self::assertSame('5171.4000', $quote->net_target);

        $mock->client->fx->getQuote('fxq_1');

        $paths = array_map(
            static fn ($r): string => $r->getMethod() . ' ' . $r->getUri()->getPath(),
            $mock->requests(),
        );
        self::assertSame([
            'GET /v1/fx/rates',
            'POST /v1/fx/quote',
            'GET /v1/fx/quote/fxq_1',
        ], $paths);
    }

    public function testRefundsSurface(): void
    {
        $mock = new HttpMock([
            HttpMock::json(201, ['id' => 'wd_1', 'object' => 'withdrawal', 'kind' => 'refund']),
            HttpMock::json(200, ['object' => 'list', 'data' => [], 'has_more' => false]),
            HttpMock::json(200, ['id' => 'wd_1', 'kind' => 'refund']),
        ]);

        $refund = $mock->client->refunds->create('ch_1', [
            'to_address' => 'TPayerAddressBase58',
            'step_up_challenge_id' => 'chal_1',
            'amount' => '50.00',
        ], ['idempotency_key' => 'refund-1']);
        self::assertSame('refund', $refund->kind);

        $mock->client->refunds->list(['status' => 'confirmed']);
        $mock->client->refunds->get('wd_1');

        $paths = array_map(
            static fn ($r): string => $r->getMethod() . ' ' . $r->getUri()->getPath(),
            $mock->requests(),
        );
        self::assertSame([
            'POST /v1/charges/ch_1/refund',
            'GET /v1/refunds',
            'GET /v1/refunds/wd_1',
        ], $paths);
        self::assertSame('refund-1', $mock->requests()[0]->getHeaderLine('Idempotency-Key'));
    }

    public function testWebhookEndpointDeleteReturnsNothing(): void
    {
        $mock = new HttpMock([new \GuzzleHttp\Psr7\Response(204)]);

        $mock->client->webhookEndpoints->delete('we_1');

        $request = $mock->lastRequest();
        self::assertSame('DELETE', $request->getMethod());
        self::assertSame('/v1/webhook_endpoints/we_1', $request->getUri()->getPath());
    }
}
