<?php

declare(strict_types=1);

namespace Liquidafy\Exception;

/**
 * 4xx client errors other than auth (401/403) and rate limit (429):
 * validation failures (400/422), not found (404), state/idempotency
 * conflicts (409). These are NEVER retried by the SDK.
 */
class InvalidRequestException extends LiquidafyAPIError
{
}
