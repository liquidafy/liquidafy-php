<?php

declare(strict_types=1);

namespace Liquidafy\Exception;

/**
 * Thrown by {@see \Liquidafy\Webhook::constructEvent()} when the
 * `Liquidafy-Signature` header cannot be verified: malformed header,
 * timestamp outside the replay-protection tolerance, or HMAC mismatch.
 *
 * Respond 400 to the webhook POST when you catch this.
 */
class SignatureVerificationException extends \Exception
{
    public function __construct(
        string $message,
        private readonly ?string $sigHeader = null,
    ) {
        parent::__construct($message);
    }

    /** The raw header value that failed verification (for debugging — contains no secret). */
    public function getSigHeader(): ?string
    {
        return $this->sigHeader;
    }
}
