<?php

declare(strict_types=1);

namespace EsLite\Search;

final readonly class SearchResponse
{
    public function __construct(
        public int $total,
        public array $hits,
        public int $tookMicros,
        public int $page,
        public int $size,
        public float $maxScore = 0.0,
        public array $facets = [],
        public array $query = [],
        public bool $cached = false,
    ) {
    }

    public function pages(): int
    {
        return $this->size > 0 ? (int) ceil($this->total / $this->size) : 0;
    }

    public function withCacheFlag(bool $cached): self
    {
        return new self(
            $this->total,
            $this->hits,
            $this->tookMicros,
            $this->page,
            $this->size,
            $this->maxScore,
            $this->facets,
            $this->query,
            $cached,
        );
    }

    public function toArray(): array
    {
        return [
            'total' => $this->total,
            'max_score' => round($this->maxScore, 6),
            'page' => $this->page,
            'size' => $this->size,
            'pages' => $this->pages(),
            'took_ms' => round($this->tookMicros / 1000, 3),
            'cached' => $this->cached,
            'query' => $this->query,
            'facets' => $this->facets,
            'hits' => array_map(static fn (SearchHit $hit): array => $hit->toArray(), $this->hits),
        ];
    }
}
