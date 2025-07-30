<?php

declare(strict_types=1);

namespace EsLite\Search\Scorer;

use EsLite\Index\DocIdIterator;
use EsLite\Ranking\Explanation;

final class ExclusionScorer implements Scorer
{
    private int $current = -1;

    public function __construct(
        private readonly Scorer $included,
        private readonly DocIdIterator $excluded,
    ) {
    }

    public function docId(): int
    {
        return $this->current;
    }

    public function next(): int
    {
        return $this->skipExcluded($this->included->next());
    }

    public function advance(int $target): int
    {
        if ($this->current >= $target && $this->current >= 0) {
            return $this->current;
        }

        return $this->skipExcluded($this->included->advance($target));
    }

    public function cost(): int
    {
        return $this->included->cost();
    }

    public function score(): float
    {
        return $this->included->score();
    }

    public function explain(int $documentId): Explanation
    {
        return new Explanation(
            'match without excluded clauses',
            $this->included->explain($documentId)->value,
            [$this->included->explain($documentId)],
        );
    }

    private function skipExcluded(int $candidate): int
    {
        while ($candidate !== self::NO_MORE_DOCS) {
            if ($this->excluded->advance($candidate) !== $candidate) {
                return $this->current = $candidate;
            }

            $candidate = $this->included->next();
        }

        return $this->current = self::NO_MORE_DOCS;
    }
}
