<?php

declare(strict_types=1);

namespace Liquidafy\HTTP;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\ClientInterface as GuzzleClientInterface;
use GuzzleHttp\Exception\GuzzleException;
use Psr\Http\Message\ResponseInterface;

use Liquidafy\Exception\ApiConnectionException;
use Liquidafy\Exception\AuthenticationException;
use Liquidafy\Exception\InvalidRequestException;
use Liquidafy\Exception\LiquidafyAPIError;
use Liquidafy\Exception\RateLimitException;
use Liquidafy\Util\Secret;

/**
 * Guzzle wrapper used by every resource.
 *
 * Responsibilities:
 *  - auth header (`Authorization: Bearer lr_...`) — key never logged,
 *    always masked in error messages;
 *  - `Liquidafy-API-Version` pinning (default 2026-05-01);
 *  - telemetry `User-Agent` (`Liquidafy-PHP/{ver} (PHP/{phpver}; {os})`);
 *  - `Idempotency-Key` injection via `$opts['idempotency_key']`;
 *  - automatic retry with exponential backoff + jitter on network
 *    errors, 5xx, and 429 (honoring `Retry-After`) — NEVER on other
 *    4xx;
 *  - mapping error responses to typed exceptions.
 */
final class HttpClient
{
    public const SDK_VERSION = '1.0.0';
    public const DEFAULT_API_VERSION = '2026-05-01';
    public const DEFAULT_BASE_URL = 'https://api.liquidafy.com';
    public const DEFAULT_TIMEOUT = 30.0;
    public const DEFAULT_MAX_RETRIES = 3;
    public const DEFAULT_RETRY_INITIAL_DELAY_MS = 1000;

    /** Hard cap on a single backoff sleep (ms) regardless of Retry-After. */
    private const MAX_RETRY_DELAY_MS = 60_000;

    private readonly GuzzleClientInterface $guzzle;
    private readonly string $apiKey;
    private readonly string $baseUrl;
    private readonly float $timeout;
    private readonly int $maxRetries;
    private readonly int $retryInitialDelayMs;
    private readonly bool $telemetry;
    private readonly string $apiVersion;

    /** @var callable(int): void sleeps for N milliseconds (injectable for tests) */
    private $sleeper;

    private ?string $appInfo = null;

    /**
     * @param array<string, mixed> $options see Client::__construct() for the documented keys
     */
    public function __construct(string $apiKey, array $options = [])
    {
        $apiKey = trim($apiKey);
        if ($apiKey === '') {
            throw new \InvalidArgumentException('Liquidafy API key must not be empty.');
        }

        $this->apiKey = $apiKey;
        $this->baseUrl = rtrim(self::stringOption($options, 'base_url', self::DEFAULT_BASE_URL), '/');
        $this->timeout = is_numeric($options['timeout'] ?? null) ? (float) $options['timeout'] : self::DEFAULT_TIMEOUT;
        $this->maxRetries = max(0, self::intOption($options, 'max_retries', self::DEFAULT_MAX_RETRIES));
        $this->retryInitialDelayMs = max(0, self::intOption($options, 'retry_initial_delay_ms', self::DEFAULT_RETRY_INITIAL_DELAY_MS));
        $this->telemetry = (bool) ($options['telemetry'] ?? true);
        $this->apiVersion = self::stringOption($options, 'api_version', self::DEFAULT_API_VERSION);

        $guzzle = $options['http_client'] ?? null;
        $this->guzzle = $guzzle instanceof GuzzleClientInterface
            ? $guzzle
            : new GuzzleClient(['timeout' => $this->timeout]);

        $sleeper = $options['sleeper'] ?? null;
        $this->sleeper = is_callable($sleeper)
            ? $sleeper
            : static function (int $ms): void {
                if ($ms > 0) {
                    usleep($ms * 1000);
                }
            };
    }

    public function setAppInfo(string $name, ?string $version = null, ?string $url = null): void
    {
        $info = trim($name);
        if ($info === '') {
            $this->appInfo = null;

            return;
        }
        if ($version !== null && $version !== '') {
            $info .= '/' . $version;
        }
        if ($url !== null && $url !== '') {
            $info .= ' (' . $url . ')';
        }
        $this->appInfo = $info;
    }

    public function baseUrl(): string
    {
        return $this->baseUrl;
    }

    public function apiVersion(): string
    {
        return $this->apiVersion;
    }

    /**
     * `Liquidafy-PHP/1.0.0 MeuApp/1.2.3 (https://meuapp.com) (PHP/8.1.5; Linux)`
     */
    public function userAgent(): string
    {
        $ua = 'Liquidafy-PHP/' . self::SDK_VERSION;
        if ($this->appInfo !== null) {
            $ua .= ' ' . $this->appInfo;
        }
        if ($this->telemetry) {
            $ua .= ' (PHP/' . PHP_VERSION . '; ' . PHP_OS_FAMILY . ')';
        }

        return $ua;
    }

    /**
     * Performs a request with automatic retries, returning the decoded
     * response or throwing a typed exception.
     *
     * @param array<string, mixed>      $query query-string parameters
     * @param array<string, mixed>|null $body  JSON body (null = no body; [] = `{}`)
     * @param array<string, mixed>      $opts  per-call options:
     *                                         - `idempotency_key` (string) sent as `Idempotency-Key`
     *                                         - `headers` (array<string,string>) extra headers
     */
    public function request(string $method, string $path, array $query = [], ?array $body = null, array $opts = []): ApiResponse
    {
        $headers = [
            'Authorization' => 'Bearer ' . $this->apiKey,
            'Accept' => 'application/json',
            'User-Agent' => $this->userAgent(),
            'Liquidafy-API-Version' => $this->apiVersion,
        ];

        $idempotencyKey = $opts['idempotency_key'] ?? null;
        if (is_string($idempotencyKey) && $idempotencyKey !== '') {
            $headers['Idempotency-Key'] = $idempotencyKey;
        }

        $extraHeaders = $opts['headers'] ?? null;
        if (is_array($extraHeaders)) {
            foreach ($extraHeaders as $name => $value) {
                if (is_string($name) && is_string($value)) {
                    $headers[$name] = $value;
                }
            }
        }

        $guzzleOpts = [
            'http_errors' => false,
            'allow_redirects' => false,
            'timeout' => $this->timeout,
        ];

        if ($query !== []) {
            $guzzleOpts['query'] = $query;
        }

        if ($body !== null) {
            $headers['Content-Type'] = 'application/json';
            $encoded = json_encode(
                $body === [] ? new \stdClass() : $body,
                JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR
            );
            $guzzleOpts['body'] = $encoded;
        }

        $guzzleOpts['headers'] = $headers;
        $url = $this->baseUrl . $path;

        $attempt = 0;
        while (true) {
            $response = null;
            $transportError = null;

            try {
                $response = $this->guzzle->request($method, $url, $guzzleOpts);
            } catch (GuzzleException $e) {
                $transportError = $e;
            }

            if ($response !== null && $response->getStatusCode() < 400) {
                return $this->toApiResponse($response);
            }

            if ($attempt < $this->maxRetries && $this->isRetryable($response)) {
                ($this->sleeper)($this->retryDelayMs($attempt, $response));
                $attempt++;
                continue;
            }

            if ($transportError !== null) {
                throw new ApiConnectionException(
                    'Could not connect to the Liquidafy API: '
                        . Secret::redact($transportError->getMessage(), $this->apiKey)
                        . ' (after ' . ($attempt + 1) . ' attempt(s))',
                    null,
                    null,
                    null,
                    null,
                    [],
                    $transportError,
                );
            }

            /** @var ResponseInterface $response guaranteed non-null here */
            throw $this->buildApiError($response);
        }
    }

    // ------------------------------------------------------------------
    // Retry policy
    // ------------------------------------------------------------------

    /**
     * Retry on: transport errors (response === null), any 5xx, and 429.
     * NEVER on other 4xx — those are deterministic client errors.
     */
    private function isRetryable(?ResponseInterface $response): bool
    {
        if ($response === null) {
            return true;
        }

        $status = $response->getStatusCode();

        return $status >= 500 || $status === 429;
    }

    /**
     * Exponential backoff: initial * 2^attempt (1s, 2s, 4s by default)
     * + up to 25% jitter. A numeric `Retry-After` on 429 takes
     * precedence. Capped at 60s.
     */
    private function retryDelayMs(int $attempt, ?ResponseInterface $response): int
    {
        if ($response !== null && $response->getStatusCode() === 429) {
            $retryAfter = $response->getHeaderLine('Retry-After');
            if ($retryAfter !== '' && ctype_digit($retryAfter)) {
                return min(((int) $retryAfter) * 1000, self::MAX_RETRY_DELAY_MS);
            }
        }

        $base = $this->retryInitialDelayMs;
        for ($i = 0; $i < $attempt; $i++) {
            $base *= 2;
            if ($base >= self::MAX_RETRY_DELAY_MS) {
                $base = self::MAX_RETRY_DELAY_MS;
                break;
            }
        }

        $jitter = $base > 0 ? random_int(0, intdiv($base, 4)) : 0;

        return min($base + $jitter, self::MAX_RETRY_DELAY_MS);
    }

    // ------------------------------------------------------------------
    // Response handling
    // ------------------------------------------------------------------

    private function toApiResponse(ResponseInterface $response): ApiResponse
    {
        return new ApiResponse(
            $response->getStatusCode(),
            $response->getHeaders(),
            $this->decodeBody($response),
            $this->extractRequestId($response),
        );
    }

    private function decodeBody(ResponseInterface $response): mixed
    {
        $raw = (string) $response->getBody();
        if ($raw === '') {
            return null;
        }

        $decoded = json_decode($raw, true);

        return $decoded === null && trim($raw) !== 'null' ? $raw : $decoded;
    }

    private function extractRequestId(ResponseInterface $response): ?string
    {
        foreach (['Request-Id', 'X-Request-Id'] as $header) {
            $value = $response->getHeaderLine($header);
            if ($value !== '') {
                return $value;
            }
        }

        return null;
    }

    private function buildApiError(ResponseInterface $response): LiquidafyAPIError
    {
        $status = $response->getStatusCode();
        $decoded = $this->decodeBody($response);

        /** @var array<string, mixed> $errorBody */
        $errorBody = [];
        if (is_array($decoded) && isset($decoded['error']) && is_array($decoded['error'])) {
            /** @var array<string, mixed> $errorBody */
            $errorBody = $decoded['error'];
        }

        $code = isset($errorBody['code']) && is_string($errorBody['code']) ? $errorBody['code'] : null;
        $type = isset($errorBody['type']) && is_string($errorBody['type']) ? $errorBody['type'] : null;
        $apiMessage = isset($errorBody['message']) && is_string($errorBody['message'])
            ? $errorBody['message']
            : 'Liquidafy API request failed.';
        $requestId = $this->extractRequestId($response);
        if ($requestId === null && isset($errorBody['request_id']) && is_string($errorBody['request_id'])) {
            $requestId = $errorBody['request_id'];
        }

        $suffix = sprintf(
            ' (status: %d%s%s)',
            $status,
            $code !== null ? ', code: ' . $code : '',
            $requestId !== null ? ', request_id: ' . $requestId : '',
        );

        if ($status === 401 || $status === 403) {
            return new AuthenticationException(
                Secret::redact($apiMessage, $this->apiKey)
                    . ' (key: ' . Secret::mask($this->apiKey) . ')' . $suffix,
                $status,
                $code,
                $type,
                $requestId,
                $errorBody,
            );
        }

        if ($status === 429) {
            $retryAfterHeader = $response->getHeaderLine('Retry-After');
            $retryAfter = $retryAfterHeader !== '' && ctype_digit($retryAfterHeader)
                ? (int) $retryAfterHeader
                : null;

            return new RateLimitException(
                Secret::redact($apiMessage, $this->apiKey) . $suffix,
                $status,
                $code,
                $type,
                $requestId,
                $errorBody,
                $retryAfter,
            );
        }

        if ($status < 500) {
            return new InvalidRequestException(
                Secret::redact($apiMessage, $this->apiKey) . $suffix,
                $status,
                $code,
                $type,
                $requestId,
                $errorBody,
            );
        }

        return new LiquidafyAPIError(
            Secret::redact($apiMessage, $this->apiKey) . $suffix,
            $status,
            $code,
            $type,
            $requestId,
            $errorBody,
        );
    }

    // ------------------------------------------------------------------
    // Option helpers
    // ------------------------------------------------------------------

    /**
     * @param array<string, mixed> $options
     */
    private static function stringOption(array $options, string $key, string $default): string
    {
        $value = $options[$key] ?? null;

        return is_string($value) && $value !== '' ? $value : $default;
    }

    /**
     * @param array<string, mixed> $options
     */
    private static function intOption(array $options, string $key, int $default): int
    {
        $value = $options[$key] ?? null;

        return is_numeric($value) ? (int) $value : $default;
    }
}
