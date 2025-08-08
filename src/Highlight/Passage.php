<?php

declare(strict_types=1);

namespace EsLite\Highlight;

final readonly class Passage
{
    public array $spans;

    public function __construct(
        public int $start,
        public int $end,
        array $spans,
        public float $score = 0.0,
        public bool $truncatedStart = false,
        public bool $truncatedEnd = false,
    ) {
        $this->spans = array_values($spans);
    }

    public function withScore(float $score): self
    {
        return new self($this->start, $this->end, $this->spans, $score, $this->truncatedStart, $this->truncatedEnd);
    }

    public function matchCount(): int
    {
        return count($this->spans);
    }

    public function distinctTerms(): int
    {
        $terms = [];

        foreach ($this->spans as $span) {
            $terms[$span->term] = true;
        }

        return count($terms);
    }
}
