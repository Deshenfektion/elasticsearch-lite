<?php

declare(strict_types=1);

namespace EsLite\Search;

use EsLite\Document\StoredDocument;
use EsLite\Ranking\Explanation;

final readonly class SearchHit
{
    public function __construct(
        public StoredDocument $document,
        public float $score,
        public array $highlights = [],
        public ?Explanation $explanation = null,
    ) {
    }

    public function toArray(): array
    {
        $hit = $this->document->toArray();
        $hit['score'] = round($this->score, 6);
        $hit['highlights'] = $this->highlights;

        if ($this->explanation !== null) {
            $hit['explanation'] = $this->explanation->toArray();
        }

        return $hit;
    }
}
