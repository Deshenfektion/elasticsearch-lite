<?php

declare(strict_types=1);

namespace EsLite\Analysis\Stemmer;

final class NoopStemmer implements Stemmer
{
    public function name(): string
    {
        return 'none';
    }

    public function stem(string $word): string
    {
        return $word;
    }
}
