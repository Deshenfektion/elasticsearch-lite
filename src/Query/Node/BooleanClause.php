<?php

declare(strict_types=1);

namespace EsLite\Query\Node;

use EsLite\Query\Occur;

final readonly class BooleanClause
{
    public function __construct(
        public Occur $occur,
        public Query $query,
    ) {
    }

    public static function must(Query $query): self
    {
        return new self(Occur::Must, $query);
    }

    public static function should(Query $query): self
    {
        return new self(Occur::Should, $query);
    }

    public static function mustNot(Query $query): self
    {
        return new self(Occur::MustNot, $query);
    }

    public function withQuery(Query $query): self
    {
        return new self($this->occur, $query);
    }

    public function describe(): string
    {
        return $this->occur->symbol() . $this->query->describe();
    }

    public function toArray(): array
    {
        return ['occur' => $this->occur->value, 'query' => $this->query->toArray()];
    }
}
