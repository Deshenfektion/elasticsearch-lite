<?php

declare(strict_types=1);

namespace EsLite\Index;

final class ArrayDocIdIterator implements DocIdIterator
{
    private readonly PostingIterator $iterator;

    public function __construct(array $docIds)
    {
        sort($docIds);
        $this->iterator = new PostingIterator($docIds);
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
}
