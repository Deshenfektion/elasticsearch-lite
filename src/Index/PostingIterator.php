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
        if ($this->size === 0 || $this->cursor >= $this->size) {
            $this->cursor = $this->size;

            return self::NO_MORE_DOCS;
        }

        $low = max($this->cursor, 0);

        if ($this->docIds[$low] >= $target && $this->cursor >= 0) {
            return $this->docIds[$low];
        }

        $step = 1;
        $high = $low;

        while ($high < $this->size && $this->docIds[$high] < $target) {
            $low = $high;
            $high += $step;
            $step <<= 1;
        }

        $high = min($high, $this->size - 1);
        $this->cursor = $this->binarySearch($low, $high, $target);

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

    private function binarySearch(int $low, int $high, int $target): int
    {
        while ($low <= $high) {
            $middle = $low + intdiv($high - $low, 2);

            if ($this->docIds[$middle] < $target) {
                $low = $middle + 1;
            } else {
                $high = $middle - 1;
            }
        }

        return $low;
    }
}
