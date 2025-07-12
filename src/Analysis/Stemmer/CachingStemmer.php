<?php

declare(strict_types=1);

namespace EsLite\Analysis\Stemmer;

use EsLite\Support\Cache\Cache;
use EsLite\Support\Cache\LruCache;

final class CachingStemmer implements Stemmer
{
    public function __construct(
        private readonly Stemmer $inner,
        private readonly Cache $cache = new LruCache(4096),
    ) {
    }

    public function name(): string
    {
        return $this->inner->name();
    }

    public function stem(string $word): string
    {
        $cached = $this->cache->get($word);

        if (is_string($cached)) {
            return $cached;
        }

        $stem = $this->inner->stem($word);
        $this->cache->put($word, $stem);

        return $stem;
    }

    public function statistics(): array
    {
        return $this->cache->statistics()->toArray();
    }
}
