<?php

declare(strict_types=1);

namespace EsLite\Support\Cache;

final class NullCache implements Cache
{
    private int $misses = 0;

    public function get(string $key): mixed
    {
        $this->misses++;

        return null;
    }

    public function put(string $key, mixed $value): void
    {
    }

    public function remember(string $key, callable $resolver): mixed
    {
        $this->misses++;

        return $resolver();
    }

    public function forget(string $key): void
    {
    }

    public function flush(): void
    {
    }

    public function statistics(): CacheStatistics
    {
        return new CacheStatistics(0, $this->misses, 0, 0, 0);
    }
}
