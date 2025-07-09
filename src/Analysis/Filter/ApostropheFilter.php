<?php

declare(strict_types=1);

namespace EsLite\Analysis\Filter;

use EsLite\Analysis\TermNormaliser;
use EsLite\Analysis\TokenFilter;
use EsLite\Analysis\TokenStream;

final class ApostropheFilter implements TokenFilter, TermNormaliser
{
    public function name(): string
    {
        return 'apostrophe';
    }

    public function apply(TokenStream $stream): TokenStream
    {
        $tokens = [];

        foreach ($stream as $token) {
            $stripped = $this->normalise($token->term);
            $tokens[] = $stripped === $token->term ? $token : $token->withTerm($stripped);
        }

        return TokenStream::fromArray($tokens);
    }

    public function normalise(string $term): string
    {
        if (!str_contains($term, "'") && !str_contains($term, "\u{2019}")) {
            return $term;
        }

        $term = str_replace("\u{2019}", "'", $term);

        if (preg_match('/^(.+)\'s$/u', $term, $matches) === 1) {
            return $matches[1];
        }

        return str_replace("'", '', $term);
    }
}
