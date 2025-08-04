<?php

declare(strict_types=1);

namespace EsLite\Search\Scorer;

use EsLite\Ranking\Explanation;
use EsLite\Search\Filter\DocumentSet;

final class FilteredScorer implements Scorer
{
    private int $current = -1;

    public function __construct(
        private readonly Scorer $inner,
        private readonly DocumentSet $allowed,
    ) {
    }

    public function docId(): int
    {
        return $this->current;
    }

    public function next(): int
    {
        return $this->skipFiltered($this->inner->next());
    }

    public function advance(int $target): int
    {
        if ($this->current >= $target && $this->current >= 0) {
            return $this->current;
        }

        return $this->skipFiltered($this->inner->advance($target));
    }

    public function cost(): int
    {
        return min($this->inner->cost(), $this->allowed->count());
    }

    public function score(): float
    {
        return $this->inner->score();
    }

    public function explain(int $documentId): Explanation
    {
        return $this->inner->explain($documentId);
    }

    private function skipFiltered(int $candidate): int
    {
        while ($candidate !== self::NO_MORE_DOCS) {
            if ($this->allowed->contains($candidate)) {
                return $this->current = $candidate;
            }

            $candidate = $this->inner->next();
        }

        return $this->current = self::NO_MORE_DOCS;
    }
}
