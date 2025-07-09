<?php

declare(strict_types=1);

namespace EsLite\Analysis\Filter;

use EsLite\Analysis\TermNormaliser;
use EsLite\Analysis\TokenFilter;
use EsLite\Analysis\TokenStream;

final class AsciiFoldingFilter implements TokenFilter, TermNormaliser
{
    private const array FOLDINGS = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'ā' => 'a', 'ă' => 'a',
        'æ' => 'ae',
        'ç' => 'c', 'ć' => 'c', 'č' => 'c',
        'ð' => 'd', 'đ' => 'd',
        'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e', 'ē' => 'e', 'ė' => 'e', 'ę' => 'e', 'ě' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ī' => 'i', 'į' => 'i',
        'ł' => 'l',
        'ñ' => 'n', 'ń' => 'n', 'ň' => 'n',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'ō' => 'o',
        'œ' => 'oe',
        'ř' => 'r',
        'ß' => 'ss', 'ś' => 's', 'š' => 's', 'ş' => 's',
        'ť' => 't', 'ţ' => 't',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ū' => 'u', 'ů' => 'u',
        'ý' => 'y', 'ÿ' => 'y',
        'ž' => 'z', 'ź' => 'z', 'ż' => 'z',
        'þ' => 'th',
    ];

    public function name(): string
    {
        return 'asciifolding';
    }

    public function apply(TokenStream $stream): TokenStream
    {
        $tokens = [];

        foreach ($stream as $token) {
            $folded = $this->normalise($token->term);
            $tokens[] = $folded === $token->term ? $token : $token->withTerm($folded);
        }

        return TokenStream::fromArray($tokens);
    }

    public function normalise(string $term): string
    {
        if (preg_match('/^[\x20-\x7e]*$/', $term) === 1) {
            return $term;
        }

        return strtr($term, self::FOLDINGS);
    }
}
