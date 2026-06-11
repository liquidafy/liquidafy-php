<?php

declare(strict_types=1);

namespace Liquidafy\Tests;

use PHPUnit\Framework\TestCase;

use Liquidafy\Tests\Support\HttpMock;
use Liquidafy\Util\LiquidafyObject;

/**
 * Cursor pagination: iterating a single page vs lazily walking every
 * page via autoPaging().
 */
final class AutoPagingTest extends TestCase
{
    /**
     * @param list<string> $ids
     * @return array<string, mixed>
     */
    private static function page(array $ids, bool $hasMore, ?string $nextCursor): array
    {
        return [
            'object' => 'list',
            'data' => array_map(
                static fn (string $id): array => ['id' => $id, 'object' => 'charge', 'amount' => '10.00'],
                $ids,
            ),
            'has_more' => $hasMore,
            'next_cursor' => $nextCursor,
        ];
    }

    public function testSinglePageIteration(): void
    {
        $mock = new HttpMock([
            HttpMock::json(200, self::page(['ch_1', 'ch_2'], true, 'cur2')),
        ]);

        $page = $mock->client->charges->list(['status' => 'confirmed']);

        $ids = [];
        foreach ($page as $charge) {
            $ids[] = $charge->id;
        }

        self::assertSame(['ch_1', 'ch_2'], $ids);
        self::assertCount(2, $page);
        self::assertTrue($page->hasMore());
        self::assertSame('cur2', $page->nextCursor());
        self::assertCount(1, $mock->requests(), 'plain iteration must not fetch the next page');
    }

    public function testAutoPagingWalksEveryPage(): void
    {
        $mock = new HttpMock([
            HttpMock::json(200, self::page(['ch_1', 'ch_2'], true, 'cur2')),
            HttpMock::json(200, self::page(['ch_3', 'ch_4'], true, 'cur3')),
            HttpMock::json(200, self::page(['ch_5'], false, null)),
        ]);

        $ids = [];
        foreach ($mock->client->charges->list(['status' => 'confirmed', 'limit' => 2])->autoPaging() as $charge) {
            self::assertInstanceOf(LiquidafyObject::class, $charge);
            $ids[] = $charge->id;
        }

        self::assertSame(['ch_1', 'ch_2', 'ch_3', 'ch_4', 'ch_5'], $ids);

        $requests = $mock->requests();
        self::assertCount(3, $requests);

        // Page 1 carries the original filters, no cursor.
        parse_str($requests[0]->getUri()->getQuery(), $q1);
        self::assertSame('confirmed', $q1['status'] ?? null);
        self::assertArrayNotHasKey('cursor', $q1);

        // Subsequent pages keep filters AND add the cursor.
        parse_str($requests[1]->getUri()->getQuery(), $q2);
        self::assertSame('confirmed', $q2['status'] ?? null);
        self::assertSame('cur2', $q2['cursor'] ?? null);

        parse_str($requests[2]->getUri()->getQuery(), $q3);
        self::assertSame('cur3', $q3['cursor'] ?? null);
    }

    public function testAutoPagingIsLazy(): void
    {
        $mock = new HttpMock([
            HttpMock::json(200, self::page(['ch_1', 'ch_2'], true, 'cur2')),
            HttpMock::json(200, self::page(['ch_3'], false, null)),
        ]);

        $iterator = $mock->client->charges->list()->autoPaging();

        // First item: only page 1 fetched.
        $iterator->current();
        self::assertCount(1, $mock->requests());

        // Consume page 1 fully + step into page 2.
        $iterator->next();
        $iterator->next();
        self::assertCount(2, $mock->requests());
        self::assertSame('ch_3', $iterator->current()?->id);
    }

    public function testAutoPagingStopsWhenHasMoreIsFalse(): void
    {
        $mock = new HttpMock([
            HttpMock::json(200, self::page(['ch_1'], false, 'cur_ignored')),
        ]);

        $ids = iterator_to_array($mock->client->charges->list()->autoPaging(), false);

        self::assertCount(1, $ids);
        self::assertCount(1, $mock->requests(), 'has_more=false must stop pagination even with a cursor present');
    }
}
