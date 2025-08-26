<?php

declare(strict_types=1);

namespace EsLite\Support\Cache;

final class ApcuCache implements Cache
{
    private int $hits = 0;

    private int $misses = 0;

    public function __construct(
        private readonly string $namespace,
        private readonly int $ttlSeconds = 0,
    ) {
    }

    public static function available(): bool
    {
        return function_exists('apcu_enabled') && apcu_enabled();
    }

    public function get(string $key): mixed
    {
        $found = false;
        $value = apcu_fetch($this->key($key), $found);

        if ($found === true) {
            $this->hits++;

            return $value;
        }

        $this->misses++;

        return null;
    }

    public function put(string $key, mixed $value): void
    {
        apcu_store($this->key($key), $value, $this->ttlSeconds);
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
        apcu_delete($this->key($key));
    }

    public function flush(): void
    {
        foreach ($this->keys() as $key) {
            apcu_delete($key);
        }
    }

    public function statistics(): CacheStatistics
    {
        return new CacheStatistics($this->hits, $this->misses, 0, count($this->keys()), 0);
    }

    private function keys(): array
    {
        $info = apcu_cache_info(false);
        $prefix = $this->namespace . ':';
        $keys = [];

        foreach ($info['cache_list'] ?? [] as $entry) {
            $key = (string) ($entry['info'] ?? '');

            if (str_starts_with($key, $prefix)) {
                $keys[] = $key;
            }
        }

        return $keys;
    }

    private function key(string $key): string
    {
        return $this->namespace . ':' . $key;
    }
}
