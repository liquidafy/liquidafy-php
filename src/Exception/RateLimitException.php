<?php

declare(strict_types=1);

namespace Liquidafy\Exception;

/**
 * 429 — too many requests. The SDK already retried (honoring
 * `Retry-After`) before surfacing this; check {@see getRetryAfter()}
 * for the server-suggested wait in seconds.
 */
class RateLimitException extends LiquidafyAPIError
{
    /**
     * @param array<string, mixed> $errorBody
     */
    public function __construct(
        string $message,
        ?int $httpStatus = null,
        ?string $errorCode = null,
        ?string $errorType = null,
        ?string $requestId = null,
        array $errorBody = [],
        private readonly ?int $retryAfter = null,
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus, $errorCode, $errorType, $requestId, $errorBody, $previous);
    }

    /** Server-suggested wait (seconds) from the `Retry-After` header, when present. */
    public function getRetryAfter(): ?int
    {
        return $this->retryAfter;
    }
}
