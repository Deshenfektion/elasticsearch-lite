<?php

declare(strict_types=1);

namespace EsLite\Query\Node;

final readonly class MatchNoneQuery implements Query
{
    public function kind(): string
    {
        return 'match_none';
    }

    public function field(): ?string
    {
        return null;
    }

    public function describe(): string
    {
        return '<none>';
    }

    public function toArray(): array
    {
        return ['type' => 'match_none'];
    }
}
