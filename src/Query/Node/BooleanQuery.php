<?php

declare(strict_types=1);

namespace EsLite\Query\Node;

use EsLite\Query\Occur;

final readonly class BooleanQuery implements Query
{
    public array $clauses;

    public function __construct(array $clauses, public int $minimumShouldMatch = 1)
    {
        $this->clauses = array_values($clauses);
    }

    public function kind(): string
    {
        return 'boolean';
    }

    public function field(): ?string
    {
        return null;
    }

    public function clausesFor(Occur $occur): array
    {
        return array_values(array_filter(
            $this->clauses,
            static fn (BooleanClause $clause): bool => $clause->occur === $occur,
        ));
    }

    public function withClauses(array $clauses): self
    {
        return new self($clauses, $this->minimumShouldMatch);
    }

    public function isEmpty(): bool
    {
        return $this->clauses === [];
    }

    public function count(): int
    {
        return count($this->clauses);
    }

    public function describe(): string
    {
        return '(' . implode(' ', array_map(
            static fn (BooleanClause $clause): string => $clause->describe(),
            $this->clauses,
        )) . ')';
    }

    public function toArray(): array
    {
        return [
            'type' => 'boolean',
            'minimum_should_match' => $this->minimumShouldMatch,
            'clauses' => array_map(static fn (BooleanClause $clause): array => $clause->toArray(), $this->clauses),
        ];
    }
}
