<?php

declare(strict_types=1);

namespace Liquidafy\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

use Liquidafy\Exception\SignatureVerificationException;
use Liquidafy\Webhook;

/**
 * Verifies Webhook::constructEvent against the Liquidafy webhook
 * signature scheme:
 *
 *     Liquidafy-Signature: t=<unix>,v1=<hex(hmac-sha256(secret, "<t>.<body>"))>
 */
final class WebhookTest extends TestCase
{
    private const SECRET = 'whsec_test_secret_0123456789';
    private const NOW = 1_750_000_000;

    private string $payload;

    protected function setUp(): void
    {
        $this->payload = json_encode([
            'id' => 'evt_01HXYZ12345ABCDEFGHJKMNPQR',
            'object' => 'event',
            'type' => 'charge.confirmed',
            'api_version' => '2026-05-01',
            'livemode' => false,
            'created' => self::NOW,
            'merchant_id' => 'mer_01HXYZ12345ABCDEFGHJKMNPQR',
            'data' => ['object' => ['id' => 'ch_01HXYZ12345ABCDEFGHJKMNPQR', 'status' => 'confirmed']],
        ], JSON_THROW_ON_ERROR);
    }

    /** Builds a wire header exactly as the Liquidafy API emits it. */
    private function header(int $timestamp, ?string $signature = null): string
    {
        $signature ??= Webhook::computeSignature(self::SECRET, $this->payload, $timestamp);

        return sprintf('t=%d,v1=%s', $timestamp, $signature);
    }

    public function testValidSignatureReturnsTypedEvent(): void
    {
        $event = Webhook::constructEvent(
            $this->payload,
            $this->header(self::NOW),
            self::SECRET,
            300,
            self::NOW,
        );

        self::assertSame('evt_01HXYZ12345ABCDEFGHJKMNPQR', $event->id);
        self::assertSame('charge.confirmed', $event->type);
        self::assertSame('confirmed', $event->data->object->status);
    }

    public function testComputeSignatureMatchesKnownVector(): void
    {
        // Independently computed: hash_hmac('sha256', "<t>.<body>", secret).
        $expected = hash_hmac('sha256', self::NOW . '.' . $this->payload, self::SECRET);

        self::assertSame($expected, Webhook::computeSignature(self::SECRET, $this->payload, self::NOW));
        self::assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $expected);
    }

    public function testInvalidSignatureIsRejected(): void
    {
        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('Signature mismatch');

        Webhook::constructEvent(
            $this->payload,
            $this->header(self::NOW, str_repeat('ab', 32)),
            self::SECRET,
            300,
            self::NOW,
        );
    }

    public function testTamperedPayloadIsRejected(): void
    {
        $header = $this->header(self::NOW);
        $tampered = str_replace('"confirmed"', '"overpaid"', $this->payload);

        $this->expectException(SignatureVerificationException::class);

        Webhook::constructEvent($tampered, $header, self::SECRET, 300, self::NOW);
    }

    public function testWrongSecretIsRejected(): void
    {
        $this->expectException(SignatureVerificationException::class);

        Webhook::constructEvent(
            $this->payload,
            $this->header(self::NOW),
            'whsec_other_secret',
            300,
            self::NOW,
        );
    }

    public function testExpiredTimestampOutsideReplayWindowIsRejected(): void
    {
        $this->expectException(SignatureVerificationException::class);
        $this->expectExceptionMessage('tolerance');

        // 301s in the past with a 300s tolerance → replay-window violation.
        Webhook::constructEvent(
            $this->payload,
            $this->header(self::NOW - 301),
            self::SECRET,
            300,
            self::NOW,
        );
    }

    public function testFutureTimestampOutsideToleranceIsRejected(): void
    {
        $this->expectException(SignatureVerificationException::class);

        Webhook::constructEvent(
            $this->payload,
            $this->header(self::NOW + 301),
            self::SECRET,
            300,
            self::NOW,
        );
    }

    public function testTimestampExactlyAtToleranceBoundaryIsAccepted(): void
    {
        // Tolerance is two-sided and inclusive: fail only when |now - t| > tol, so ±300 passes.
        $past = Webhook::constructEvent($this->payload, $this->header(self::NOW - 300), self::SECRET, 300, self::NOW);
        $future = Webhook::constructEvent($this->payload, $this->header(self::NOW + 300), self::SECRET, 300, self::NOW);

        self::assertSame('charge.confirmed', $past->type);
        self::assertSame('charge.confirmed', $future->type);
    }

    public function testDefaultToleranceIs300Seconds(): void
    {
        self::assertSame(300, Webhook::DEFAULT_TOLERANCE);

        // Signed "now" with the real clock and the default tolerance.
        $ts = time();
        $event = Webhook::constructEvent($this->payload, $this->header($ts), self::SECRET);
        self::assertSame('charge.confirmed', $event->type);
    }

    #[DataProvider('malformedHeaders')]
    public function testMalformedHeadersAreRejected(string $header): void
    {
        $this->expectException(SignatureVerificationException::class);

        Webhook::constructEvent($this->payload, $header, self::SECRET, 300, self::NOW);
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function malformedHeaders(): array
    {
        return [
            'empty' => [''],
            'garbage' => ['garbage'],
            'missing v1' => ['t=' . self::NOW],
            'missing t' => ['v1=' . str_repeat('a', 64)],
            'non-digit timestamp' => ['t=12a4,v1=' . str_repeat('a', 64)],
            'negative timestamp' => ['t=-1234,v1=' . str_repeat('a', 64)],
            'empty timestamp' => ['t=,v1=' . str_repeat('a', 64)],
            'part without equals' => ['t=1750000000,v1abc'],
        ];
    }

    public function testUnknownSchemeKeysAreIgnored(): void
    {
        $sig = Webhook::computeSignature(self::SECRET, $this->payload, self::NOW);
        $header = sprintf('t=%d,v0=legacy,v1=%s', self::NOW, $sig);

        $event = Webhook::constructEvent($this->payload, $header, self::SECRET, 300, self::NOW);

        self::assertSame('charge.confirmed', $event->type);
    }

    public function testNonJsonPayloadWithValidSignatureThrowsUnexpectedValue(): void
    {
        $payload = 'not-json';
        $sig = Webhook::computeSignature(self::SECRET, $payload, self::NOW);
        $header = sprintf('t=%d,v1=%s', self::NOW, $sig);

        $this->expectException(\UnexpectedValueException::class);

        Webhook::constructEvent($payload, $header, self::SECRET, 300, self::NOW);
    }
}
