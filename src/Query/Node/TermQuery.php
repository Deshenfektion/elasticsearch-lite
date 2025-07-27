<?php

declare(strict_types=1);

namespace EsLite\Query\Node;

final readonly class TermQuery implements Query
{
    public function __construct(
        public string $term,
        public ?string $field = null,
        public float $boost = 1.0,
    ) {
    }

    public function kind(): string
    {
        return 'term';
    }

    public function field(): ?string
    {
        return $this->field;
    }

    public function withTerm(string $term): self
    {
        return new self($term, $this->field, $this->boost);
    }

    public function withBoost(float $boost): self
    {
        return new self($this->term, $this->field, $boost);
    }

    public function describe(): string
    {
        return ($this->field === null ? '' : $this->field . ':') . $this->term;
    }

    public function toArray(): array
    {
        return ['type' => 'term', 'field' => $this->field, 'term' => $this->term, 'boost' => $this->boost];
    }
}
