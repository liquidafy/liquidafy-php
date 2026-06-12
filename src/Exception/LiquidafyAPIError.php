<?php

declare(strict_types=1);

namespace Liquidafy\Exception;

/**
 * Base exception for every error returned by (or while talking to)
 * the Liquidafy API.
 *
 * Exposes the canonical error envelope fields:
 * `error.code`, `error.type`, `error.message`, `error.request_id`
 * plus the HTTP status. The raw API key is NEVER present in the
 * message — only the masked form (`lr_live_***...123`).
 */
class LiquidafyAPIError extends \Exception
{
    /**
     * @param array<string, mixed> $errorBody decoded `error` object from the response body
     */
    public function __construct(
        string $message,
        private readonly ?int $httpStatus = null,
        private readonly ?string $errorCode = null,
        private readonly ?string $errorType = null,
        private readonly ?string $requestId = null,
        private readonly array $errorBody = [],
        ?\Throwable $previous = null,
    ) {
        parent::__construct($message, $httpStatus ?? 0, $previous);
    }

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    /** Canonical, stable error code (e.g. `invalid_amount`) — safe to `switch` on. */
    public function getErrorCode(): ?string
    {
        return $this->errorCode;
    }

    /** Error class (`validation_error`, `rate_limit_error`, `server_error`, ...). */
    public function getErrorType(): ?string
    {
        return $this->errorType;
    }

    /** Request id (`req_...`) — quote it when contacting support. */
    public function getRequestId(): ?string
    {
        return $this->requestId;
    }

    /**
     * @return array<string, mixed>
     */
    public function getErrorBody(): array
    {
        return $this->errorBody;
    }
}
