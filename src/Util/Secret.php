<?php

declare(strict_types=1);

namespace Liquidafy\Util;

/**
 * Helpers for handling secret values (API keys, webhook secrets).
 *
 * The SDK NEVER logs or embeds a raw API key in an exception message.
 * Every message that needs to reference the key goes through mask().
 */
final class Secret
{
    private function __construct()
    {
    }

    /**
     * Masks an API key for safe display in error messages.
     *
     * `lr_live_abcdefgh1234...x123` => `lr_live_***...123`
     *
     * Keeps the recognizable prefix (up to and including the second
     * underscore) plus the last 3 characters; everything else is
     * replaced with `***...`.
     */
    public static function mask(string $key): string
    {
        $key = trim($key);
        if ($key === '') {
            return '(empty)';
        }

        $prefix = '';
        if (preg_match('/^([a-z]+_[a-z]+_)/', $key, $m) === 1) {
            $prefix = $m[1];
        }

        // Too short to safely reveal a suffix.
        if (strlen($key) <= strlen($prefix) + 4) {
            return $prefix . '***';
        }

        return $prefix . '***...' . substr($key, -3);
    }

    /**
     * Replaces any occurrence of the raw key inside an arbitrary
     * message (e.g. a transport exception bubbling up from Guzzle)
     * with the masked form.
     */
    public static function redact(string $message, string $key): string
    {
        if ($key === '') {
            return $message;
        }

        return str_replace($key, self::mask($key), $message);
    }
}
