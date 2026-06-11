<?php

declare(strict_types=1);

namespace Liquidafy\Tests;

use PHPUnit\Framework\TestCase;

use Liquidafy\Exception\AuthenticationException;
use Liquidafy\Exception\InvalidRequestException;
use Liquidafy\Exception\LiquidafyAPIError;
use Liquidafy\Exception\RateLimitException;
use Liquidafy\Tests\Support\HttpMock;

/**
 * Error envelope (`{error: {code, message, type, request_id}}`) →
 * typed exception mapping.
 */
final class ErrorMappingTest extends TestCase
{
    public function test401MapsToAuthenticationException(): void
    {
        $mock = new HttpMock([
            HttpMock::error(401, 'invalid_api_key', 'authentication_error', 'Authentication failed'),
        ], ['max_retries' => 0]);

        try {
            $mock->client->charges->get('ch_1');
            self::fail('Expected AuthenticationException');
        } catch (AuthenticationException $e) {
            self::assertSame(401, $e->getHttpStatus());
            self::assertSame('invalid_api_key', $e->getErrorCode());
            self::assertSame('authentication_error', $e->getErrorType());
            self::assertSame('req_TEST123', $e->getRequestId());
        }
    }

    public function test403MapsToAuthenticationException(): void
    {
        $mock = new HttpMock([
            HttpMock::error(403, 'permission_denied', 'permission_error', 'Missing permission'),
        ], ['max_retries' => 0]);

        $this->expectException(AuthenticationException::class);

        $mock->client->withdrawals->create(['type' => 'crypto', 'amount' => '10.00', 'currency' => 'USDT', 'chain' => 'tron', 'to_address' => 'T...']);
    }

    public function test404MapsToInvalidRequestException(): void
    {
        $mock = new HttpMock([
            HttpMock::error(404, 'not_found', 'not_found_error', 'Charge not found.'),
        ], ['max_retries' => 0]);

        try {
            $mock->client->charges->get('ch_missing');
            self::fail('Expected InvalidRequestException');
        } catch (InvalidRequestException $e) {
            self::assertSame(404, $e->getHttpStatus());
            self::assertSame('not_found', $e->getErrorCode());
        }
    }

    public function test409ConflictMapsToInvalidRequestException(): void
    {
        $mock = new HttpMock([
            HttpMock::error(409, 'idempotency_conflict', 'idempotency_error', 'Key reused with different payload'),
        ], ['max_retries' => 0]);

        $this->expectException(InvalidRequestException::class);

        $mock->client->charges->create(['amount' => '1.00', 'currency' => 'USDT'], ['idempotency_key' => 'k1']);
    }

    public function test422MapsToInvalidRequestException(): void
    {
        $mock = new HttpMock([
            HttpMock::error(422, 'invalid_destination', 'validation_error', 'Destination invalid'),
        ], ['max_retries' => 0]);

        $this->expectException(InvalidRequestException::class);

        $mock->client->withdrawals->create(['type' => 'fiat', 'amount_usdt' => '50.00', 'target_currency' => 'BRL', 'quote_id' => 'fxq_1', 'destination' => ['kind' => 'pix_key', 'value' => 'x']]);
    }

    public function test429MapsToRateLimitExceptionWithRetryAfter(): void
    {
        $mock = new HttpMock([
            HttpMock::error(429, 'rate_limited', 'rate_limit_error', 'Too many requests', ['Retry-After' => '30']),
        ], ['max_retries' => 0]);

        try {
            $mock->client->charges->get('ch_1');
            self::fail('Expected RateLimitException');
        } catch (RateLimitException $e) {
            self::assertSame(429, $e->getHttpStatus());
            self::assertSame(30, $e->getRetryAfter());
            self::assertSame('rate_limit_error', $e->getErrorType());
        }
    }

    public function test5xxMapsToBaseApiError(): void
    {
        $mock = new HttpMock([
            HttpMock::error(503, 'fx_provider_unavailable', 'external_service_error', 'Provider down'),
        ], ['max_retries' => 0]);

        try {
            $mock->client->fx->rates();
            self::fail('Expected LiquidafyAPIError');
        } catch (LiquidafyAPIError $e) {
            self::assertNotInstanceOf(InvalidRequestException::class, $e);
            self::assertNotInstanceOf(AuthenticationException::class, $e);
            self::assertSame(503, $e->getHttpStatus());
            self::assertSame('fx_provider_unavailable', $e->getErrorCode());
        }
    }

    public function testRequestIdHeaderTakesPrecedenceOverBody(): void
    {
        $mock = new HttpMock([
            HttpMock::error(400, 'invalid_json', 'validation_error', 'bad', ['Request-Id' => 'req_FROM_HEADER']),
        ], ['max_retries' => 0]);

        try {
            $mock->client->charges->get('ch_1');
            self::fail('Expected InvalidRequestException');
        } catch (InvalidRequestException $e) {
            self::assertSame('req_FROM_HEADER', $e->getRequestId());
        }
    }

    public function testErrorBodyIsAccessible(): void
    {
        $mock = new HttpMock([
            HttpMock::error(400, 'invalid_amount', 'validation_error', 'amount must be positive'),
        ], ['max_retries' => 0]);

        try {
            $mock->client->charges->get('ch_1');
            self::fail('Expected InvalidRequestException');
        } catch (InvalidRequestException $e) {
            self::assertSame('invalid_amount', $e->getErrorBody()['code'] ?? null);
            self::assertStringContainsString('amount must be positive', $e->getMessage());
            self::assertStringContainsString('request_id: req_TEST123', $e->getMessage());
        }
    }
}
