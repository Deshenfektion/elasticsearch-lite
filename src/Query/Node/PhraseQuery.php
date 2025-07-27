<?php

declare(strict_types=1);

namespace EsLite\Query\Node;

final readonly class PhraseQuery implements Query
{
    public array $terms;

    public array $offsets;

    public function __construct(
        array $terms,
        public ?string $field = null,
        array $offsets = [],
        public float $boost = 1.0,
    ) {
        $this->terms = array_values($terms);
        $this->offsets = $offsets === []
            ? range(0, max(count($this->terms) - 1, 0))
            : array_values($offsets);
    }

    public function kind(): string
    {
        return 'phrase';
    }

    public function field(): ?string
    {
        return $this->field;
    }

    public function withTerms(array $terms, array $offsets = []): self
    {
        return new self($terms, $this->field, $offsets, $this->boost);
    }

    public function length(): int
    {
        return count($this->terms);
    }

    public function hasGaps(): bool
    {
        return $this->offsets !== range(0, max(count($this->terms) - 1, 0));
    }

    public function describe(): string
    {
        return ($this->field === null ? '' : $this->field . ':') . '"' . implode(' ', $this->terms) . '"';
    }

    public function toArray(): array
    {
        return [
            'type' => 'phrase',
            'field' => $this->field,
            'terms' => $this->terms,
            'offsets' => $this->offsets,
            'boost' => $this->boost,
        ];
    }
}
