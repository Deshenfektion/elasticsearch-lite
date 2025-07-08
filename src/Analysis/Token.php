<?php

declare(strict_types=1);

namespace EsLite\Analysis;

final readonly class Token
{
    public function __construct(
        public string $term,
        public int $position,
        public int $startOffset,
        public int $endOffset,
    ) {
    }

    public function withTerm(string $term): self
    {
        return new self($term, $this->position, $this->startOffset, $this->endOffset);
    }

    public function length(): int
    {
        return $this->endOffset - $this->startOffset;
    }
}
