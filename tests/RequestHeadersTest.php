<?php

declare(strict_types=1);

namespace Liquidafy\Tests;

use PHPUnit\Framework\TestCase;

use Liquidafy\Tests\Support\HttpMock;

/**
 * Idempotency-Key injection, API version pinning, auth header, and
 * User-Agent telemetry.
 */
final class RequestHeadersTest extends TestCase
{
    public function testIdempotencyKeyHeaderIsInjected(): void
    {
        $mock = new HttpMock([
            HttpMock::json(201, ['id' => 'ch_1', 'object' => 'charge', 'status' => 'pending']),
        ]);

        $mock->client->charges->create(
            ['amount' => '100.00', 'currency' => 'USDT', 'description' => 'Pedido #1234'],
            ['idempotency_key' => 'order-1234'],
        );

        $request = $mock->lastRequest();
        self::assertSame('order-1234', $request->getHeaderLine('Idempotency-Key'));
        self::assertSame('POST', $request->getMethod());
        self::assertSame('/v1/charges', $request->getUri()->getPath());

        // Money stays a decimal string on the wire.
        $body = json_decode((string) $request->getBody(), true);
        self::assertIsArray($body);
        self::assertSame('100.00', $body['amount']);
    }

    public function testNoIdempotencyHeaderWhenNotProvided(): void
    {
        $mock = new HttpMock([HttpMock::json(200, ['id' => 'ch_1'])]);

        $mock->client->charges->get('ch_1');

        self::assertFalse($mock->lastRequest()->hasHeader('Idempotency-Key'));
    }

    public function testStandardHeadersArePresent(): void
    {
        $mock = new HttpMock([HttpMock::json(200, ['id' => 'ch_1'])]);

        $mock->client->charges->get('ch_1');

        $request = $mock->lastRequest();
        self::assertSame('Bearer ' . HttpMock::TEST_KEY, $request->getHeaderLine('Authorization'));
        self::assertSame('2026-05-01', $request->getHeaderLine('Liquidafy-API-Version'));
        self::assertSame('application/json', $request->getHeaderLine('Accept'));

        $ua = $request->getHeaderLine('User-Agent');
        self::assertStringStartsWith('Liquidafy-PHP/1.0.0', $ua);
        self::assertStringContainsString('PHP/' . PHP_VERSION, $ua);
    }

    public function testApiVersionCanBePinned(): void
    {
        $mock = new HttpMock([HttpMock::json(200, ['id' => 'ch_1'])], ['api_version' => '2026-05-01']);

        $mock->client->charges->get('ch_1');

        self::assertSame('2026-05-01', $mock->lastRequest()->getHeaderLine('Liquidafy-API-Version'));
    }

    public function testAppInfoIsAppendedToUserAgent(): void
    {
        $mock = new HttpMock([HttpMock::json(200, ['id' => 'ch_1'])]);
        $mock->client->setAppInfo('MeuApp', '1.2.3', 'https://meuapp.com');

        $mock->client->charges->get('ch_1');

        self::assertStringContainsString(
            'Liquidafy-PHP/1.0.0 MeuApp/1.2.3 (https://meuapp.com)',
            $mock->lastRequest()->getHeaderLine('User-Agent'),
        );
    }

    public function testTelemetryCanBeDisabled(): void
    {
        $mock = new HttpMock([HttpMock::json(200, ['id' => 'ch_1'])], ['telemetry' => false]);

        $mock->client->charges->get('ch_1');

        self::assertSame('Liquidafy-PHP/1.0.0', $mock->lastRequest()->getHeaderLine('User-Agent'));
    }

    public function testFloatMoneyIsRejectedBeforeAnyRequest(): void
    {
        $mock = new HttpMock([HttpMock::json(201, ['id' => 'ch_1'])]);

        try {
            /* @phpstan-ignore-next-line intentional misuse */
            $mock->client->charges->create(['amount' => 100.00, 'currency' => 'USDT']);
            self::fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            self::assertStringContainsString('decimal string', $e->getMessage());
        }

        self::assertCount(0, $mock->requests(), 'No request may be sent with a float amount');
    }

    public function testPathIdsAreUrlEncoded(): void
    {
        $mock = new HttpMock([HttpMock::json(200, ['id' => 'weird'])]);

        $mock->client->charges->get('ch_1/../../admin');

        self::assertSame(
            '/v1/charges/ch_1%2F..%2F..%2Fadmin',
            $mock->lastRequest()->getUri()->getPath(),
        );
    }
}
