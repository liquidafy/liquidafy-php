<?php

declare(strict_types=1);

namespace Liquidafy\Tests\Support;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Psr\Http\Message\RequestInterface;

use Liquidafy\Client;

/**
 * Test harness: Liquidafy Client wired to a Guzzle MockHandler with
 * request history and a fake (recording) sleeper so retry/backoff
 * tests run instantly.
 */
final class HttpMock
{
    public const TEST_KEY = 'lr_test_abcdefghijklmnop123';

    public MockHandler $mock;

    /** @var array<int, array<string, mixed>> */
    public array $history = [];

    /** @var list<int> recorded backoff sleeps in milliseconds */
    public array $sleeps = [];

    public Client $client;

    /**
     * @param list<mixed>          $responses Guzzle MockHandler queue (Response or \Throwable)
     * @param array<string, mixed> $options   extra Client options
     */
    public function __construct(array $responses, array $options = [], string $apiKey = self::TEST_KEY)
    {
        $this->mock = new MockHandler($responses);
        $stack = HandlerStack::create($this->mock);
        $stack->push(Middleware::history($this->history));

        $options['http_client'] = new GuzzleClient(['handler' => $stack]);
        $options['sleeper'] = function (int $ms): void {
            $this->sleeps[] = $ms;
        };
        $options['base_url'] = $options['base_url'] ?? 'https://api.test.local';

        $this->client = new Client($apiKey, $options);
    }

    /**
     * @param array<string, mixed>  $body
     * @param array<string, string> $headers
     */
    public static function json(int $status, array $body, array $headers = []): Response
    {
        return new Response(
            $status,
            $headers + ['Content-Type' => 'application/json'],
            json_encode($body, JSON_THROW_ON_ERROR),
        );
    }

    /**
     * Canonical API error response body.
     *
     * @param array<string, string> $headers
     */
    public static function error(int $status, string $code, string $type, string $message, array $headers = [], string $requestId = 'req_TEST123'): Response
    {
        return self::json($status, [
            'error' => [
                'code' => $code,
                'type' => $type,
                'message' => $message,
                'request_id' => $requestId,
            ],
        ], $headers);
    }

    /**
     * @return list<RequestInterface>
     */
    public function requests(): array
    {
        $out = [];
        foreach ($this->history as $entry) {
            $request = $entry['request'] ?? null;
            if ($request instanceof RequestInterface) {
                $out[] = $request;
            }
        }

        return $out;
    }

    public function lastRequest(): RequestInterface
    {
        $requests = $this->requests();
        if ($requests === []) {
            throw new \LogicException('No request was recorded.');
        }

        return $requests[count($requests) - 1];
    }
}
