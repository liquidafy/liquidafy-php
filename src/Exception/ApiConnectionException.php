<?php

declare(strict_types=1);

namespace Liquidafy\Exception;

/**
 * Network-level failure (DNS, connection refused, timeout) after the
 * SDK exhausted its automatic retries. The underlying transport
 * exception is attached as `previous`; its message is redacted so the
 * raw API key can never leak through transport error text.
 */
class ApiConnectionException extends LiquidafyAPIError
{
}
