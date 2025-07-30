<?php

declare(strict_types=1);

namespace EsLite\Search\Scorer;

use EsLite\Index\DocIdIterator;
use EsLite\Ranking\Explanation;

interface Scorer extends DocIdIterator
{
    public function score(): float;

    public function explain(int $documentId): Explanation;
}
