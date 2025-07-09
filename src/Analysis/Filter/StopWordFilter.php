<?php

declare(strict_types=1);

namespace EsLite\Analysis\Filter;

use EsLite\Analysis\StopWords;
use EsLite\Analysis\TokenFilter;
use EsLite\Analysis\TokenStream;

final class StopWordFilter implements TokenFilter
{
    public function __construct(private readonly StopWords $stopWords)
    {
    }

    public function name(): string
    {
        return 'stop';
    }

    public function apply(TokenStream $stream): TokenStream
    {
        $tokens = [];

        foreach ($stream as $token) {
            if ($this->stopWords->has($token->term)) {
                continue;
            }

            $tokens[] = $token;
        }

        return TokenStream::fromArray($tokens);
    }

    public function stopWords(): StopWords
    {
        return $this->stopWords;
    }
}
