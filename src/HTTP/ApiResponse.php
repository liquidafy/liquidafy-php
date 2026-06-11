<?php

declare(strict_types=1);

namespace Liquidafy\HTTP;

/**
 * Decoded HTTP response handed from the transport to the resources.
 */
final class ApiResponse
{
    /**
     * @param array<string, list<string>> $headers
     */
    public function __construct(
        public readonly int $status,
        public readonly array $headers,
        public readonly mixed $data,
        public readonly ?string $requestId,
    ) {
    }

    /**
     * Decoded JSON body as an associative array (empty array for
     * 204 / non-JSON bodies).
     *
     * @return array<string, mixed>
     */
    public function json(): array
    {
        if (is_array($this->data) && !array_is_list($this->data)) {
            /** @var array<string, mixed> $data */
            $data = $this->data;

            return $data;
        }

        return [];
    }
}
