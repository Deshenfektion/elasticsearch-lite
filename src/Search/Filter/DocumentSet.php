<?php

declare(strict_types=1);

namespace EsLite\Search\Filter;

use EsLite\Index\ArrayDocIdIterator;

final class DocumentSet
{
    private readonly array $lookup;

    private readonly array $ids;

    public function __construct(array $documentIds)
    {
        $ids = array_values(array_unique(array_map(intval(...), $documentIds)));
        sort($ids);
        $this->ids = $ids;
        $this->lookup = array_fill_keys($ids, true);
    }

    public static function empty(): self
    {
        return new self([]);
    }

    public function contains(int $documentId): bool
    {
        return isset($this->lookup[$documentId]);
    }

    public function ids(): array
    {
        return $this->ids;
    }

    public function count(): int
    {
        return count($this->ids);
    }

    public function isEmpty(): bool
    {
        return $this->ids === [];
    }

    public function iterator(): ArrayDocIdIterator
    {
        return new ArrayDocIdIterator($this->ids);
    }
}
