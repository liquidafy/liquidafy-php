<?php

declare(strict_types=1);

namespace Liquidafy\Tests;

use PHPUnit\Framework\TestCase;

use Liquidafy\Client;
use Liquidafy\Exception\AuthenticationException;
use Liquidafy\Tests\Support\HttpMock;
use Liquidafy\Util\Secret;

/**
 * The raw API key must NEVER appear in any error message — only the
 * masked form (`lr_live_***...123`).
 */
final class KeyMaskingTest extends TestCase
{
    public function testMaskKeepsPrefixAndLast3Chars(): void
    {
        self::assertSame('lr_live_***...123', Secret::mask('lr_live_abcdefgh123'));
        self::assertSame('lr_test_***...xyz', Secret::mask('lr_test_abcdefghxyz'));
    }

    public function testMaskShortKeysRevealNothing(): void
    {
        self::assertSame('lr_live_***', Secret::mask('lr_live_ab'));
        self::assertSame('(empty)', Secret::mask(''));
    }

    public function testRedactReplacesRawKeyInsideArbitraryText(): void
    {
        $key = 'lr_test_supersecret456';
        $redacted = Secret::redact("cURL error: sent Authorization: Bearer {$key} and failed", $key);

        self::assertStringNotContainsString($key, $redacted);
        self::assertStringContainsString('lr_test_***...456', $redacted);
    }

    public function testAuthenticationErrorMessageContainsMaskedKeyOnly(): void
    {
        $mock = new HttpMock([
            HttpMock::error(401, 'invalid_api_key', 'authentication_error', 'Authentication failed'),
        ], ['max_retries' => 0]);

        try {
            $mock->client->charges->get('ch_1');
            self::fail('Expected AuthenticationException');
        } catch (AuthenticationException $e) {
            self::assertStringNotContainsString(HttpMock::TEST_KEY, $e->getMessage());
            self::assertStringContainsString(Secret::mask(HttpMock::TEST_KEY), $e->getMessage());
            self::assertStringContainsString('request_id: req_TEST123', $e->getMessage());
        }
    }

    public function testInvalidKeyPrefixRejectedWithMaskedMessage(): void
    {
        $rawKey = 'sk_live_thisIsNotALiquidafyKey999';

        try {
            new Client($rawKey);
            self::fail('Expected InvalidArgumentException');
        } catch (\InvalidArgumentException $e) {
            self::assertStringNotContainsString($rawKey, $e->getMessage());
            self::assertStringContainsString('lr_live_', $e->getMessage());
        }
    }

    public function testEmptyKeyIsRejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        new Client('   ');
    }
}
