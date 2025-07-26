<?php

declare(strict_types=1);

namespace EsLite\Query;

final readonly class Token
{
    public function __construct(
        public TokenType $type,
        public string $value,
        public int $position,
    ) {
    }

    public function is(TokenType ...$types): bool
    {
        return in_array($this->type, $types, true);
    }
}
