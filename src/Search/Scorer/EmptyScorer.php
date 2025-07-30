<?php

declare(strict_types=1);

namespace EsLite\Search\Scorer;

use EsLite\Ranking\Explanation;

final class EmptyScorer implements Scorer
{
    public function docId(): int
    {
        return self::NO_MORE_DOCS;
    }

    public function next(): int
    {
        return self::NO_MORE_DOCS;
    }

    public function advance(int $target): int
    {
        return self::NO_MORE_DOCS;
    }

    public function cost(): int
    {
        return 0;
    }

    public function score(): float
    {
        return 0.0;
    }

    public function explain(int $documentId): Explanation
    {
        return Explanation::of('no matching terms', 0.0);
    }
}
