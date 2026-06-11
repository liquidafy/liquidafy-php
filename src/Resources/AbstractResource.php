<?php

declare(strict_types=1);

namespace Liquidafy\Resources;

use Liquidafy\HTTP\HttpClient;
use Liquidafy\Util\Collection;
use Liquidafy\Util\LiquidafyObject;

/**
 * Shared plumbing for API resources.
 */
abstract class AbstractResource
{
    public function __construct(protected readonly HttpClient $http)
    {
    }

    /**
     * @param array<string, mixed>      $query
     * @param array<string, mixed>|null $body
     * @param array<string, mixed>      $opts
     */
    protected function requestObject(string $method, string $path, array $query = [], ?array $body = null, array $opts = []): LiquidafyObject
    {
        $response = $this->http->request($method, $path, $query, $body, $opts);

        return new LiquidafyObject($response->json());
    }

    /**
     * @param array<string, mixed> $params query-string filters (status, cursor, limit, ...)
     * @param array<string, mixed> $opts
     */
    protected function requestCollection(string $path, array $params = [], array $opts = []): Collection
    {
        $fetch = function (array $query) use ($path, $opts): array {
            return $this->http->request('GET', $path, $query, null, $opts)->json();
        };

        return Collection::fromResponse($fetch($params), $params, $fetch);
    }

    /**
     * URL-encodes a path segment (resource id).
     */
    protected function pathId(string $id): string
    {
        $id = trim($id);
        if ($id === '') {
            throw new \InvalidArgumentException('Resource id must not be empty.');
        }

        return rawurlencode($id);
    }

    /**
     * Money MUST be a decimal string ("100.00") — floats silently lose
     * precision and are rejected up front.
     *
     * @param array<string, mixed> $params
     * @param list<string>         $fields
     */
    protected function assertMoneyStrings(array $params, array $fields): void
    {
        foreach ($fields as $field) {
            if (array_key_exists($field, $params) && !is_string($params[$field])) {
                throw new \InvalidArgumentException(
                    sprintf(
                        "'%s' must be a decimal string (e.g. '100.00'), %s given — never use floats for money.",
                        $field,
                        get_debug_type($params[$field]),
                    )
                );
            }
        }
    }
}
