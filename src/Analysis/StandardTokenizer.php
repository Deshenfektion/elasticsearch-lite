<?php

declare(strict_types=1);

namespace EsLite\Analysis;

final class StandardTokenizer implements Tokenizer
{
    private const string PATTERN = '/\p{N}+(?:[.,]\p{N}+)*|[\p{L}\p{N}]+(?:[\'\x{2019}][\p{L}\p{N}]+)*/u';

    public function tokenize(string $text): TokenStream
    {
        if ($text === '') {
            return new TokenStream();
        }

        $matches = [];
        $found = preg_match_all(self::PATTERN, $text, $matches, PREG_OFFSET_CAPTURE);

        if ($found === false || $found === 0) {
            return new TokenStream();
        }

        $tokens = [];
        $position = 0;

        foreach ($matches[0] as [$value, $offset]) {
            $tokens[] = new Token($value, $position++, $offset, $offset + strlen($value));
        }

        return TokenStream::fromArray($tokens);
    }
}
