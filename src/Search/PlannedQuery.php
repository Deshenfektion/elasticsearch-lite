<?php

declare(strict_types=1);

namespace EsLite\Search;

use EsLite\Query\Node\Query;

final readonly class PlannedQuery
{
    public function __construct(
        public Query $original,
        public Query $rewritten,
        public array $terms,
        public bool $needsPositions,
        public array $phrases = [],
    ) {
    }

    public function isEmpty(): bool
    {
        return $this->terms === [] && $this->rewritten->kind() !== 'match_all';
    }

    public function toArray(): array
    {
        return [
            'parsed' => $this->original->describe(),
            'rewritten' => $this->rewritten->describe(),
            'terms' => $this->terms,
            'positions_loaded' => $this->needsPositions,
        ];
    }
}
