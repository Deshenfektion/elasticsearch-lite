<?php

declare(strict_types=1);

namespace EsLite\Query\Node;

final readonly class WildcardQuery implements Query
{
    public function __construct(
        public string $pattern,
        public ?string $field = null,
        public float $boost = 1.0,
    ) {
    }

    public function kind(): string
    {
        return 'wildcard';
    }

    public function field(): ?string
    {
        return $this->field;
    }

    public function withPattern(string $pattern): self
    {
        return new self($pattern, $this->field, $this->boost);
    }

    public function describe(): string
    {
        return ($this->field === null ? '' : $this->field . ':') . $this->pattern;
    }

    public function toArray(): array
    {
        return ['type' => 'wildcard', 'field' => $this->field, 'pattern' => $this->pattern, 'boost' => $this->boost];
    }
}
