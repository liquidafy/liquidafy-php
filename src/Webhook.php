<?php

declare(strict_types=1);

namespace Liquidafy;

use Liquidafy\Exception\SignatureVerificationException;
use Liquidafy\Util\LiquidafyObject;

/**
 * Webhook signature verification.
 *
 * Implements the Liquidafy webhook signature scheme:
 *
 *     Liquidafy-Signature: t=<unix-seconds>,v1=<hex(hmac-sha256(secret, "<t>.<body>"))>
 *
 * The signed message is the literal decimal timestamp, a `.`, and the
 * RAW request body (NOT the parsed/re-encoded JSON). Comparison is
 * constant-time (`hash_equals`); the timestamp must be within the
 * replay-protection tolerance (default 300s) of "now" in either
 * direction.
 *
 * ```php
 * $payload = file_get_contents('php://input');
 * $sig     = $_SERVER['HTTP_LIQUIDAFY_SIGNATURE'] ?? '';
 *
 * try {
 *     $event = \Liquidafy\Webhook::constructEvent($payload, $sig, $endpointSecret);
 * } catch (\Liquidafy\Exception\SignatureVerificationException $e) {
 *     http_response_code(400);
 *     exit;
 * }
 * ```
 */
final class Webhook
{
    /** Default replay-protection window in seconds. */
    public const DEFAULT_TOLERANCE = 300;

    public const SIGNATURE_HEADER = 'Liquidafy-Signature';

    private function __construct()
    {
    }

    /**
     * Verifies the `Liquidafy-Signature` header against the RAW
     * payload and returns the decoded event.
     *
     * @param string   $payload   raw request body, exactly as received
     * @param string   $sigHeader value of the `Liquidafy-Signature` header
     * @param string   $secret    endpoint signing secret (from create/rotate)
     * @param int      $tolerance replay window in seconds (default 300)
     * @param int|null $now       current unix time override (testing only)
     *
     * @throws SignatureVerificationException when the header is
     *                                        malformed, the timestamp is outside the tolerance, or
     *                                        the signature does not match
     * @throws \UnexpectedValueException      when the payload is not a JSON object
     */
    public static function constructEvent(
        string $payload,
        string $sigHeader,
        string $secret,
        int $tolerance = self::DEFAULT_TOLERANCE,
        ?int $now = null,
    ): LiquidafyObject {
        self::verifyHeader($payload, $sigHeader, $secret, $tolerance, $now);

        $decoded = json_decode($payload, true);
        if (!is_array($decoded) || array_is_list($decoded)) {
            throw new \UnexpectedValueException('Webhook payload is not a valid JSON object.');
        }

        /** @var array<string, mixed> $decoded */
        return new LiquidafyObject($decoded);
    }

    /**
     * Verification core — throws on any failure:
     *   - missing/malformed header                 → fail
     *   - non-digit / missing `t`, missing `v1`    → fail
     *   - |now − t| > tolerance                    → fail (replay window)
     *   - constant-time HMAC mismatch              → fail
     *
     * @throws SignatureVerificationException
     */
    public static function verifyHeader(
        string $payload,
        string $sigHeader,
        string $secret,
        int $tolerance = self::DEFAULT_TOLERANCE,
        ?int $now = null,
    ): void {
        $parsed = self::parseHeader($sigHeader);
        if ($parsed === null) {
            throw new SignatureVerificationException(
                'Unable to extract timestamp and signature from the ' . self::SIGNATURE_HEADER . ' header.',
                $sigHeader,
            );
        }

        [$timestamp, $signature] = $parsed;

        $nowTs = $now ?? time();
        $diff = $nowTs - $timestamp;
        if ($diff < -$tolerance || $diff > $tolerance) {
            throw new SignatureVerificationException(
                sprintf('Timestamp outside the allowed tolerance of %d seconds.', $tolerance),
                $sigHeader,
            );
        }

        $expected = self::computeSignature($secret, $payload, $timestamp);
        if (!hash_equals($expected, $signature)) {
            throw new SignatureVerificationException(
                'Signature mismatch for the expected payload — wrong secret, tampered body, or body re-encoded before verification (always pass the RAW request body).',
                $sigHeader,
            );
        }
    }

    /**
     * `hex(hmac-sha256(secret, "<timestamp>.<payload>"))` — the exact
     * signature the Liquidafy API sends. Useful for building test
     * fixtures and local webhook simulators.
     */
    public static function computeSignature(string $secret, string $payload, int $timestamp): string
    {
        return hash_hmac('sha256', $timestamp . '.' . $payload, $secret);
    }

    /**
     * Parses `t=<unix>,v1=<hex>`: every comma-separated part must be
     * `k=v`; unknown keys are ignored (forward compatibility with new
     * scheme versions); `t` must be ASCII digits only; on duplicates
     * the last value wins; both `t` and `v1` are required.
     *
     * @return array{0: int, 1: string}|null
     */
    private static function parseHeader(string $header): ?array
    {
        if ($header === '') {
            return null;
        }

        $timestamp = 0;
        $signature = '';

        foreach (explode(',', $header) as $part) {
            $kv = explode('=', trim($part), 2);
            if (count($kv) !== 2) {
                return null;
            }
            if ($kv[0] === 't') {
                if ($kv[1] === '' || !ctype_digit($kv[1])) {
                    return null;
                }
                $timestamp = (int) $kv[1];
            } elseif ($kv[0] === 'v1') {
                $signature = $kv[1];
            }
        }

        if ($timestamp === 0 || $signature === '') {
            return null;
        }

        return [$timestamp, $signature];
    }
}
