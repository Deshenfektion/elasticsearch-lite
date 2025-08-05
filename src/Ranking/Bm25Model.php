<?php

declare(strict_types=1);

namespace EsLite\Ranking;

final class Bm25Model implements ScoringModel
{
    public function __construct(
        private readonly float $k1 = 1.2,
        private readonly float $b = 0.75,
    ) {
    }

    public function name(): string
    {
        return 'bm25';
    }

    public function idf(int $documentFrequency, int $collectionSize): float
    {
        if ($collectionSize <= 0) {
            return 0.0;
        }

        $documentFrequency = min(max($documentFrequency, 0), $collectionSize);

        return log(1.0 + ($collectionSize - $documentFrequency + 0.5) / ($documentFrequency + 0.5));
    }

    public function termScore(float $idf, int $termFrequency, int $fieldLength, float $averageFieldLength): float
    {
        if ($termFrequency <= 0) {
            return 0.0;
        }

        $lengthRatio = $averageFieldLength > 0.0 ? $fieldLength / $averageFieldLength : 1.0;
        $denominator = $termFrequency + $this->k1 * (1.0 - $this->b + $this->b * $lengthRatio);

        if ($denominator <= 0.0) {
            return 0.0;
        }

        return $idf * ($termFrequency * ($this->k1 + 1.0)) / $denominator;
    }

    public function parameters(): array
    {
        return ['k1' => $this->k1, 'b' => $this->b];
    }
}
