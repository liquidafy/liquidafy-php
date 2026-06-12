<?php

declare(strict_types=1);

namespace Liquidafy\Util;

/**
 * A page of a paginated list response
 * (`{"object":"list","data":[...],"next_cursor":...,"has_more":...}`).
 *
 * Iterating the Collection itself walks the CURRENT page only.
 * Use {@see Collection::autoPaging()} to lazily walk every page —
 * the iterator follows `next_cursor` automatically and stops when
 * `has_more` is false:
 *
 * ```php
 * foreach ($liquidafy->charges->list(['status' => 'confirmed'])->autoPaging() as $charge) {
 *     echo $charge->id, PHP_EOL;
 * }
 * ```
 *
 * @implements \IteratorAggregate<int, LiquidafyObject>
 */
final class Collection extends LiquidafyObject implements \IteratorAggregate, \Countable
{
    /** @var callable(array<string, mixed>): array<string, mixed> */
    private $fetcher;

    /**
     * @param array<string, mixed>                                $payload  decoded list response body
     * @param array<string, mixed>                                $params   query params used to fetch this page
     * @param callable(array<string, mixed>): array<string, mixed> $fetcher fetches the decoded body for given params
     */
    private function __construct(array $payload, private array $params, callable $fetcher)
    {
        parent::__construct($payload);
        $this->fetcher = $fetcher;
    }

    /**
     * @param array<string, mixed>                                $payload
     * @param array<string, mixed>                                $params
     * @param callable(array<string, mixed>): array<string, mixed> $fetcher
     */
    public static function fromResponse(array $payload, array $params, callable $fetcher): self
    {
        return new self($payload, $params, $fetcher);
    }

    /**
     * Items on the current page.
     *
     * @return list<LiquidafyObject>
     */
    public function data(): array
    {
        $data = $this->raw['data'] ?? [];
        if (!is_array($data)) {
            return [];
        }

        $items = [];
        foreach ($data as $item) {
            if (is_array($item) && !array_is_list($item)) {
                /** @var array<string, mixed> $item */
                $items[] = new LiquidafyObject($item);
            }
        }

        return $items;
    }

    public function hasMore(): bool
    {
        return (bool) ($this->raw['has_more'] ?? false);
    }

    public function nextCursor(): ?string
    {
        $cursor = $this->raw['next_cursor'] ?? null;

        return is_string($cursor) && $cursor !== '' ? $cursor : null;
    }

    /**
     * @return \Traversable<int, LiquidafyObject>
     */
    public function getIterator(): \Traversable
    {
        yield from $this->data();
    }

    public function count(): int
    {
        return count($this->data());
    }

    /**
     * Lazily iterates EVERY item across ALL pages, fetching the next
     * page via `cursor` as needed. Network calls happen on demand.
     *
     * @return \Generator<int, LiquidafyObject>
     */
    public function autoPaging(): \Generator
    {
        $page = $this;

        while (true) {
            foreach ($page->data() as $item) {
                yield $item;
            }

            $cursor = $page->nextCursor();
            if (!$page->hasMore() || $cursor === null) {
                return;
            }

            $params = $page->params;
            $params['cursor'] = $cursor;
            $page = new self(($this->fetcher)($params), $params, $this->fetcher);
        }
    }
}
