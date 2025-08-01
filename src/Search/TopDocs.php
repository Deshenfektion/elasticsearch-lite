<?php

declare(strict_types=1);

namespace EsLite\Search;

final readonly class TopDocs
{
    public array $scoreDocs;

    public function __construct(
        public int $totalHits,
        array $scoreDocs,
        public float $maxScore = 0.0,
        public array $matchedIds = [],
    ) {
        $this->scoreDocs = array_values($scoreDocs);
    }

    public static function empty(): self
    {
        return new self(0, []);
    }

    public function documentIds(): array
    {
        return array_map(static fn (ScoreDoc $doc): int => $doc->documentId, $this->scoreDocs);
    }

    public function scores(): array
    {
        $scores = [];

        foreach ($this->scoreDocs as $scoreDoc) {
            $scores[$scoreDoc->documentId] = $scoreDoc->score;
        }

        return $scores;
    }

    public function isEmpty(): bool
    {
        return $this->scoreDocs === [];
    }
}
