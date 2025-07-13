<?php

declare(strict_types=1);

namespace EsLite\Analysis\Filter;

use EsLite\Analysis\Stemmer\Stemmer;
use EsLite\Analysis\TokenFilter;
use EsLite\Analysis\TokenStream;

final class StemFilter implements TokenFilter
{
    public function __construct(private readonly Stemmer $stemmer)
    {
    }

    public function name(): string
    {
        return 'stem:' . $this->stemmer->name();
    }

    public function apply(TokenStream $stream): TokenStream
    {
        $tokens = [];

        foreach ($stream as $token) {
            $stem = $this->stemmer->stem($token->term);
            $tokens[] = $stem === $token->term ? $token : $token->withTerm($stem);
        }

        return TokenStream::fromArray($tokens);
    }

    public function stemmer(): Stemmer
    {
        return $this->stemmer;
    }
}
