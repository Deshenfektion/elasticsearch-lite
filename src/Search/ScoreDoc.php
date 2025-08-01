<?php

declare(strict_types=1);

namespace EsLite\Search;

final readonly class ScoreDoc
{
    public function __construct(
        public int $documentId,
        public float $score,
    ) {
    }
}
