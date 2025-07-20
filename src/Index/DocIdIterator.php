<?php

declare(strict_types=1);

namespace EsLite\Index;

interface DocIdIterator
{
    public const int NO_MORE_DOCS = PHP_INT_MAX;

    public function docId(): int;

    public function next(): int;

    public function advance(int $target): int;

    public function cost(): int;
}
