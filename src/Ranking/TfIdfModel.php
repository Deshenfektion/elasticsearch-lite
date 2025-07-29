<?php

declare(strict_types=1);

namespace EsLite\Ranking;

final class TfIdfModel implements ScoringModel
{
    public function __construct(private readonly bool $lengthNormalisation = true)
    {
    }

    public function name(): string
    {
        return 'tfidf';
    }

    public function idf(int $documentFrequency, int $collectionSize): float
    {
        if ($collectionSize <= 0) {
            return 0.0;
        }

        return 1.0 + log($collectionSize / (1 + max($documentFrequency, 0)));
    }

    public function termScore(float $idf, int $termFrequency, int $fieldLength, float $averageFieldLength): float
    {
        if ($termFrequency <= 0) {
            return 0.0;
        }

        $tf = sqrt($termFrequency);
        $norm = $this->lengthNormalisation && $fieldLength > 0 ? 1.0 / sqrt($fieldLength) : 1.0;

        return $tf * $idf * $idf * $norm;
    }

    public function parameters(): array
    {
        return ['length_normalisation' => $this->lengthNormalisation];
    }
}
