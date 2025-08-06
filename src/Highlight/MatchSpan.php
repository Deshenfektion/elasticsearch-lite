<?php

declare(strict_types=1);

namespace EsLite\Highlight;

final readonly class MatchSpan
{
    public function __construct(
        public int $start,
        public int $end,
        public string $term,
        public bool $phrase = false,
    ) {
    }

    public function weight(): float
    {
        return $this->phrase ? 3.0 : 1.0;
    }

    public function overlaps(self $other): bool
    {
        return $this->start < $other->end && $other->start < $this->end;
    }
}
