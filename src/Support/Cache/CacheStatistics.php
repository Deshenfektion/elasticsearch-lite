<?php

declare(strict_types=1);

namespace EsLite\Support\Cache;

final readonly class CacheStatistics
{
    public function __construct(
        public int $hits,
        public int $misses,
        public int $evictions,
        public int $entries,
        public int $capacity,
    ) {
    }

    public function hitRatio(): float
    {
        $lookups = $this->hits + $this->misses;

        return $lookups === 0 ? 0.0 : round($this->hits / $lookups, 4);
    }

    public function toArray(): array
    {
        return [
            'hits' => $this->hits,
            'misses' => $this->misses,
            'evictions' => $this->evictions,
            'entries' => $this->entries,
            'capacity' => $this->capacity,
            'hit_ratio' => $this->hitRatio(),
        ];
    }
}
