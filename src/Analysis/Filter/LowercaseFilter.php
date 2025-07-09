<?php

declare(strict_types=1);

namespace EsLite\Analysis\Filter;

use EsLite\Analysis\Token;
use EsLite\Analysis\TermNormaliser;
use EsLite\Analysis\TokenFilter;
use EsLite\Analysis\TokenStream;

final class LowercaseFilter implements TokenFilter, TermNormaliser
{
    public function name(): string
    {
        return 'lowercase';
    }

    public function apply(TokenStream $stream): TokenStream
    {
        $tokens = [];

        foreach ($stream as $token) {
            $lowered = mb_strtolower($token->term, 'UTF-8');
            $tokens[] = $lowered === $token->term ? $token : $token->withTerm($lowered);
        }

        return TokenStream::fromArray($tokens);
    }

    public function normalise(string $term): string
    {
        return mb_strtolower($term, 'UTF-8');
    }
}
