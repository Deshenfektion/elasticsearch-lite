<?php

declare(strict_types=1);

namespace EsLite\Analysis\Filter;

use EsLite\Analysis\TokenFilter;
use EsLite\Analysis\TokenStream;

final class LengthFilter implements TokenFilter
{
    public function __construct(
        private readonly int $min = 2,
        private readonly int $max = 40,
    ) {
    }

    public function name(): string
    {
        return 'length';
    }

    public function apply(TokenStream $stream): TokenStream
    {
        $tokens = [];

        foreach ($stream as $token) {
            $length = mb_strlen($token->term, 'UTF-8');

            if ($length < $this->min || $length > $this->max) {
                continue;
            }

            $tokens[] = $token;
        }

        return TokenStream::fromArray($tokens);
    }

    public function accepts(string $term): bool
    {
        $length = mb_strlen($term, 'UTF-8');

        return $length >= $this->min && $length <= $this->max;
    }
}
