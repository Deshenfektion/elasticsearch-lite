<?php

declare(strict_types=1);

namespace EsLite\Index;

use EsLite\Support\Cache\Cache;
use EsLite\Support\Cache\LruCache;
use EsLite\Support\Config;

final class IndexCache
{
    public function __construct(
        private readonly Cache $terms,
        private readonly Cache $postings,
    ) {
    }

    public static function fromConfig(Config $config): self
    {
        return new self(
            new LruCache($config->int('app.search.cache.terms', 8192)),
            new LruCache($config->int('app.search.cache.postings', 512)),
        );
    }

    public function terms(): Cache
    {
        return $this->terms;
    }

    public function postings(): Cache
    {
        return $this->postings;
    }

    public function invalidate(array $terms): void
    {
        foreach ($terms as $term) {
            $this->terms->forget($term);
            $this->postings->forget($term . ':1');
            $this->postings->forget($term . ':0');
        }
    }

    public function flush(): void
    {
        $this->terms->flush();
        $this->postings->flush();
    }

    public function statistics(): array
    {
        return [
            'terms' => $this->terms->statistics()->toArray(),
            'postings' => $this->postings->statistics()->toArray(),
        ];
    }
}
