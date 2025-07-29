<?php

declare(strict_types=1);

namespace EsLite\Ranking;

interface ScoringModel
{
    public function name(): string;

    public function idf(int $documentFrequency, int $collectionSize): float;

    public function termScore(
        float $idf,
        int $termFrequency,
        int $fieldLength,
        float $averageFieldLength,
    ): float;

    public function parameters(): array;
}
