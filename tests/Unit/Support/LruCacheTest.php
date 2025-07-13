<?php

declare(strict_types=1);

namespace EsLite\Tests\Unit\Support;

use DateTimeImmutable;
use EsLite\Support\Cache\LruCache;
use EsLite\Support\Cache\NullCache;
use EsLite\Support\Clock;
use PHPUnit\Framework\TestCase;

final class LruCacheTest extends TestCase
{
    public function testStoresAndReturnsValues(): void
    {
        $cache = new LruCache(4);
        $cache->put('index', ['df' => 12]);

        self::assertSame(['df' => 12], $cache->get('index'));
        self::assertNull($cache->get('missing'));
    }

    public function testEvictsTheLeastRecentlyUsedEntry(): void
    {
        $cache = new LruCache(2);
        $cache->put('a', 1);
        $cache->put('b', 2);
        $cache->get('a');
        $cache->put('c', 3);

        self::assertSame(1, $cache->get('a'));
        self::assertNull($cache->get('b'));
        self::assertSame(3, $cache->get('c'));
    }

    public function testRememberResolvesOnlyOnMiss(): void
    {
        $cache = new LruCache(4);
        $calls = 0;
        $resolver = static function () use (&$calls): string {
            $calls++;

            return 'value';
        };

        self::assertSame('value', $cache->remember('key', $resolver));
        self::assertSame('value', $cache->remember('key', $resolver));
        self::assertSame(1, $calls);
    }

    public function testTracksHitsMissesAndEvictions(): void
    {
        $cache = new LruCache(1);
        $cache->put('a', 1);
        $cache->get('a');
        $cache->get('b');
        $cache->put('c', 3);

        $statistics = $cache->statistics();

        self::assertSame(1, $statistics->hits);
        self::assertSame(1, $statistics->misses);
        self::assertSame(1, $statistics->evictions);
        self::assertSame(0.5, $statistics->hitRatio());
    }

    public function testEntriesExpireAfterTheirTimeToLive(): void
    {
        $clock = new class implements Clock {
            public int $offset = 0;

            public function now(): DateTimeImmutable
            {
                return (new DateTimeImmutable('2025-08-01 12:00:00'))->modify(sprintf('+%d seconds', $this->offset));
            }

            public function monotonic(): float
            {
                return (float) $this->offset;
            }
        };

        $cache = new LruCache(4, 30, $clock);
        $cache->put('query', 'results');

        self::assertSame('results', $cache->get('query'));

        $clock->offset = 31;

        self::assertNull($cache->get('query'));
    }

    public function testFlushRemovesEverything(): void
    {
        $cache = new LruCache(4);
        $cache->put('a', 1);
        $cache->flush();

        self::assertNull($cache->get('a'));
        self::assertSame(0, $cache->statistics()->entries);
    }

    public function testForgetRemovesASingleKey(): void
    {
        $cache = new LruCache(4);
        $cache->put('a', 1);
        $cache->put('b', 2);
        $cache->forget('a');

        self::assertNull($cache->get('a'));
        self::assertSame(2, $cache->get('b'));
    }

    public function testNullCacheNeverStores(): void
    {
        $cache = new NullCache();
        $cache->put('a', 1);

        self::assertNull($cache->get('a'));
        self::assertSame('resolved', $cache->remember('a', static fn (): string => 'resolved'));
        self::assertSame(0, $cache->statistics()->hits);
    }
}
