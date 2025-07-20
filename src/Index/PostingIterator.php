<?php

declare(strict_types=1);

namespace EsLite\Index;

final class PostingIterator implements DocIdIterator
{
    private int $cursor = -1;

    private readonly int $size;

    public function __construct(private readonly array $docIds)
    {
        $this->size = count($docIds);
    }

    public function docId(): int
    {
        if ($this->cursor < 0) {
            return -1;
        }

        return $this->cursor >= $this->size ? self::NO_MORE_DOCS : $this->docIds[$this->cursor];
    }

    public function next(): int
    {
        $this->cursor++;

        return $this->docId();
    }

    public function advance(int $target): int
    {
        if ($this->size === 0) {
            $this->cursor = $this->size;

            return self::NO_MORE_DOCS;
        }

        if ($this->cursor < 0) {
            $this->cursor = 0;
        }

        while ($this->cursor < $this->size && $this->docIds[$this->cursor] < $target) {
            $this->cursor++;
        }

        return $this->docId();
    }

    public function cost(): int
    {
        return $this->size;
    }

    public function reset(): void
    {
        $this->cursor = -1;
    }
}
