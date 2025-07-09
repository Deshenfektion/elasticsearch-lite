<?php

declare(strict_types=1);

namespace EsLite\Analysis;

interface TokenFilter
{
    public function name(): string;

    public function apply(TokenStream $stream): TokenStream;
}
