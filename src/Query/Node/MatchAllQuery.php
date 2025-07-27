<?php

declare(strict_types=1);

namespace EsLite\Query\Node;

final readonly class MatchAllQuery implements Query
{
    public function kind(): string
    {
        return 'match_all';
    }

    public function field(): ?string
    {
        return null;
    }

    public function describe(): string
    {
        return '*:*';
    }

    public function toArray(): array
    {
        return ['type' => 'match_all'];
    }
}
