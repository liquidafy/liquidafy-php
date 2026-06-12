<?php

declare(strict_types=1);

namespace Liquidafy\Exception;

/**
 * 401 / 403 — invalid or missing API key, or insufficient permission.
 * The message references the MASKED key only.
 */
class AuthenticationException extends LiquidafyAPIError
{
}
