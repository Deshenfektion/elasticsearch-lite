<?php

declare(strict_types=1);

namespace EsLite\Search\Scorer;

use EsLite\Index\DocIdIterator;
use EsLite\Ranking\Explanation;

final class ConstantScoreScorer implements Scorer
{
    public function __construct(
        private readonly DocIdIterator $iterator,
        private readonly float $constant = 1.0,
        private readonly string $description = 'constant score',
    ) {
    }

    public function docId(): int
    {
        return $this->iterator->docId();
    }

    public function next(): int
    {
        return $this->iterator->next();
    }

    public function advance(int $target): int
    {
        return $this->iterator->advance($target);
    }

    public function cost(): int
    {
        return $this->iterator->cost();
    }

    public function score(): float
    {
        return $this->constant;
    }

    public function explain(int $documentId): Explanation
    {
        return Explanation::of($this->description, $this->constant);
    }
}
