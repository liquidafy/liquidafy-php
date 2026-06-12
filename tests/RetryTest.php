<?php

declare(strict_types=1);

namespace Liquidafy\Tests;

use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request;
use PHPUnit\Framework\TestCase;

use Liquidafy\Exception\ApiConnectionException;
use Liquidafy\Exception\InvalidRequestException;
use Liquidafy\Exception\LiquidafyAPIError;
use Liquidafy\Tests\Support\HttpMock;

/**
 * Auto-retry policy: network errors, 5xx, and 429 (honoring
 * Retry-After) are retried with exponential backoff + jitter
 * (1s, 2s, 4s default); other 4xx are NEVER retried.
 */
final class RetryTest extends TestCase
{
    public function testServerErrorsAreRetriedUntilSuccess(): void
    {
        $mock = new HttpMock([
            HttpMock::error(500, 'internal_error', 'server_error', 'boom'),
            HttpMock::error(502, 'bad_gateway', 'server_error', 'boom'),
            HttpMock::json(200, ['id' => 'ch_1', 'object' => 'charge', 'status' => 'pending']),
        ]);

        $charge = $mock->client->charges->get('ch_1');

        self::assertSame('pending', $charge->status);
        self::assertCount(3, $mock->requests());

        // Backoff: 1s then 2s, each with up to +25% jitter.
        self::assertCount(2, $mock->sleeps);
        self::assertGreaterThanOrEqual(1000, $mock->sleeps[0]);
        self::assertLessThanOrEqual(1250, $mock->sleeps[0]);
        self::assertGreaterThanOrEqual(2000, $mock->sleeps[1]);
        self::assertLessThanOrEqual(2500, $mock->sleeps[1]);
    }

    public function testNetworkErrorsAreRetried(): void
    {
        $mock = new HttpMock([
            new ConnectException('Connection refused', new Request('GET', 'https://api.test.local/v1/charges/ch_1')),
            HttpMock::json(200, ['id' => 'ch_1', 'object' => 'charge', 'status' => 'confirmed']),
        ]);

        $charge = $mock->client->charges->get('ch_1');

        self::assertSame('confirmed', $charge->status);
        self::assertCount(2, $mock->requests());
        self::assertCount(1, $mock->sleeps);
    }

    public function test429HonorsRetryAfterHeader(): void
    {
        $mock = new HttpMock([
            HttpMock::error(429, 'rate_limited', 'rate_limit_error', 'slow down', ['Retry-After' => '7']),
            HttpMock::json(200, ['id' => 'ch_1', 'object' => 'charge']),
        ]);

        $mock->client->charges->get('ch_1');

        self::assertCount(2, $mock->requests());
        self::assertSame([7000], $mock->sleeps);
    }

    public function testClientErrorsAreNeverRetried(): void
    {
        $mock = new HttpMock([
            HttpMock::error(400, 'invalid_amount', 'validation_error', 'amount must be a decimal string'),
        ]);

        try {
            $mock->client->charges->create(['amount' => '100.00', 'currency' => 'USDT']);
            self::fail('Expected InvalidRequestException');
        } catch (InvalidRequestException $e) {
            self::assertSame(400, $e->getHttpStatus());
            self::assertSame('invalid_amount', $e->getErrorCode());
        }

        self::assertCount(1, $mock->requests(), '4xx must not be retried');
        self::assertSame([], $mock->sleeps);
    }

    public function testRetriesExhaustedSurfacesApiError(): void
    {
        $mock = new HttpMock([
            HttpMock::error(500, 'internal_error', 'server_error', 'boom'),
            HttpMock::error(500, 'internal_error', 'server_error', 'boom'),
            HttpMock::error(500, 'internal_error', 'server_error', 'boom'),
        ], ['max_retries' => 2]);

        try {
            $mock->client->charges->get('ch_1');
            self::fail('Expected LiquidafyAPIError');
        } catch (LiquidafyAPIError $e) {
            self::assertSame(500, $e->getHttpStatus());
            self::assertSame('internal_error', $e->getErrorCode());
        }

        self::assertCount(3, $mock->requests(), 'initial attempt + 2 retries');
    }

    public function testNetworkErrorsExhaustedSurfaceConnectionException(): void
    {
        $request = new Request('GET', 'https://api.test.local/v1/charges/ch_1');
        $mock = new HttpMock([
            new ConnectException('DNS failure', $request),
            new ConnectException('DNS failure', $request),
        ], ['max_retries' => 1]);

        $this->expectException(ApiConnectionException::class);
        $this->expectExceptionMessage('Could not connect');

        $mock->client->charges->get('ch_1');
    }

    public function testCustomRetryOptionsAreApplied(): void
    {
        $mock = new HttpMock([
            HttpMock::error(500, 'internal_error', 'server_error', 'boom'),
            HttpMock::json(200, ['id' => 'ch_1']),
        ], ['max_retries' => 5, 'retry_initial_delay_ms' => 100]);

        $mock->client->charges->get('ch_1');

        self::assertCount(1, $mock->sleeps);
        self::assertGreaterThanOrEqual(100, $mock->sleeps[0]);
        self::assertLessThanOrEqual(125, $mock->sleeps[0]);
    }

    public function testZeroRetriesFailsImmediately(): void
    {
        $mock = new HttpMock([
            HttpMock::error(503, 'upstream_error', 'external_service_error', 'down'),
        ], ['max_retries' => 0]);

        $this->expectException(LiquidafyAPIError::class);

        try {
            $mock->client->charges->get('ch_1');
        } finally {
            self::assertCount(1, $mock->requests());
            self::assertSame([], $mock->sleeps);
        }
    }
}
