<?php

declare(strict_types=1);

namespace EsLite\Support\Cache;

use EsLite\Support\Clock;
use EsLite\Support\SystemClock;

final class LruCache implements Cache
{
    private array $entries = [];

    private array $expiry = [];

    private int $hits = 0;

    private int $misses = 0;

    private int $evictions = 0;

    public function __construct(
        private readonly int $capacity = 1024,
        private readonly int $ttlSeconds = 0,
        private readonly Clock $clock = new SystemClock(),
    ) {
    }

    public function get(string $key): mixed
    {
        if (!array_key_exists($key, $this->entries)) {
            $this->misses++;

            return null;
        }

        if ($this->expired($key)) {
            $this->forget($key);
            $this->misses++;

            return null;
        }

        $value = $this->entries[$key];
        unset($this->entries[$key]);
        $this->entries[$key] = $value;
        $this->hits++;

        return $value;
    }

    public function put(string $key, mixed $value): void
    {
        unset($this->entries[$key]);
        $this->entries[$key] = $value;

        if ($this->ttlSeconds > 0) {
            $this->expiry[$key] = $this->clock->now()->getTimestamp() + $this->ttlSeconds;
        }

        while (count($this->entries) > $this->capacity) {
            $oldest = array_key_first($this->entries);

            if ($oldest === null) {
                break;
            }

            unset($this->entries[$oldest], $this->expiry[$oldest]);
            $this->evictions++;
        }
    }

    public function remember(string $key, callable $resolver): mixed
    {
        $cached = $this->get($key);

        if ($cached !== null) {
            return $cached;
        }

        $value = $resolver();
        $this->put($key, $value);

        return $value;
    }

    public function forget(string $key): void
    {
        unset($this->entries[$key], $this->expiry[$key]);
    }

    public function flush(): void
    {
        $this->entries = [];
        $this->expiry = [];
    }

    public function statistics(): CacheStatistics
    {
        return new CacheStatistics(
            $this->hits,
            $this->misses,
            $this->evictions,
            count($this->entries),
            $this->capacity,
        );
    }

    private function expired(string $key): bool
    {
        if (!isset($this->expiry[$key])) {
            return false;
        }

        return $this->expiry[$key] < $this->clock->now()->getTimestamp();
    }
}
