<?php

declare(strict_types=1);

namespace Liquidafy\Util;

/**
 * Immutable, read-only wrapper around an API response payload.
 *
 * Provides ergonomic property access (`$charge->checkout_url`) plus
 * ArrayAccess (`$charge['checkout_url']`). Nested associative arrays
 * are lazily wrapped; lists are returned with each associative item
 * wrapped.
 *
 * Monetary amounts are ALWAYS decimal strings (never floats) — the
 * SDK passes them through untouched to preserve precision.
 *
 * @implements \ArrayAccess<string, mixed>
 */
class LiquidafyObject implements \ArrayAccess, \JsonSerializable
{
    /**
     * @param array<string, mixed> $raw
     */
    public function __construct(protected array $raw = [])
    {
    }

    public function __get(string $name): mixed
    {
        return $this->wrap($this->raw[$name] ?? null);
    }

    public function __isset(string $name): bool
    {
        return isset($this->raw[$name]);
    }

    public function offsetExists(mixed $offset): bool
    {
        return is_string($offset) && array_key_exists($offset, $this->raw);
    }

    public function offsetGet(mixed $offset): mixed
    {
        if (!is_string($offset)) {
            return null;
        }

        return $this->wrap($this->raw[$offset] ?? null);
    }

    public function offsetSet(mixed $offset, mixed $value): void
    {
        throw new \LogicException('Liquidafy API objects are read-only.');
    }

    public function offsetUnset(mixed $offset): void
    {
        throw new \LogicException('Liquidafy API objects are read-only.');
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return $this->raw;
    }

    /**
     * @return array<string, mixed>
     */
    public function jsonSerialize(): array
    {
        return $this->raw;
    }

    private function wrap(mixed $value): mixed
    {
        if (!is_array($value)) {
            return $value;
        }

        if ($value === [] || array_is_list($value)) {
            return array_map(
                static fn (mixed $item): mixed => is_array($item) && !array_is_list($item)
                    ? new LiquidafyObject($item)
                    : $item,
                $value
            );
        }

        /** @var array<string, mixed> $value */
        return new LiquidafyObject($value);
    }
}
