<?php

declare(strict_types=1);

namespace EsLite\Query\Node;

interface Query
{
    public function kind(): string;

    public function field(): ?string;

    public function describe(): string;

    public function toArray(): array;
}
