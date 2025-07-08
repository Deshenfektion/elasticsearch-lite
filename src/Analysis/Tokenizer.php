<?php

declare(strict_types=1);

namespace EsLite\Analysis;

interface Tokenizer
{
    public function tokenize(string $text): TokenStream;
}
