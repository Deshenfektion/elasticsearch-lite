<?php

declare(strict_types=1);

namespace EsLite\Analysis\Stemmer;

interface Stemmer
{
    public function name(): string;

    public function stem(string $word): string;
}
