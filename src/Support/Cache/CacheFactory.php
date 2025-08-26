<?php

declare(strict_types=1);

namespace EsLite\Support\Cache;

final class CacheFactory
{
    public static function make(string $namespace, int $capacity, int $ttlSeconds = 0, bool $shared = false): Cache
    {
        if ($capacity <= 0) {
            return new NullCache();
        }

        if ($shared && ApcuCache::available()) {
            return new ApcuCache($namespace, $ttlSeconds);
        }

        return new LruCache($capacity, $ttlSeconds);
    }
}
