<?php

declare(strict_types=1);

namespace EsLite\Query\Node;

final readonly class PrefixQuery implements Query
{
    public function __construct(
        public string $prefix,
        public ?string $field = null,
        public float $boost = 1.0,
    ) {
    }

    public function kind(): string
    {
        return 'prefix';
    }

    public function field(): ?string
    {
        return $this->field;
    }

    public function withPrefix(string $prefix): self
    {
        return new self($prefix, $this->field, $this->boost);
    }

    public function describe(): string
    {
        return ($this->field === null ? '' : $this->field . ':') . $this->prefix . '*';
    }

    public function toArray(): array
    {
        return ['type' => 'prefix', 'field' => $this->field, 'prefix' => $this->prefix, 'boost' => $this->boost];
    }
}
