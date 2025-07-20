<?php

declare(strict_types=1);

namespace EsLite\Index;

final class PostingList
{
    private array $byDocument = [];

    private array $docIds = [];

    public function __construct(
        public readonly string $term,
        public readonly int $termId,
        public readonly int $documentFrequency,
        array $postings = [],
    ) {
        foreach ($postings as $posting) {
            $this->byDocument[$posting->documentId][$posting->fieldId] = $posting;
        }

        $this->docIds = array_keys($this->byDocument);
        sort($this->docIds);
    }

    public static function empty(string $term): self
    {
        return new self($term, 0, 0);
    }

    public function docIds(): array
    {
        return $this->docIds;
    }

    public function size(): int
    {
        return count($this->docIds);
    }

    public function isEmpty(): bool
    {
        return $this->docIds === [];
    }

    public function iterator(?int $fieldId = null): PostingIterator
    {
        return new PostingIterator($fieldId === null ? $this->docIds : $this->docIdsForField($fieldId));
    }

    public function docIdsForField(int $fieldId): array
    {
        $docIds = [];

        foreach ($this->docIds as $documentId) {
            if (isset($this->byDocument[$documentId][$fieldId])) {
                $docIds[] = $documentId;
            }
        }

        return $docIds;
    }

    public function postings(int $documentId): array
    {
        return $this->byDocument[$documentId] ?? [];
    }

    public function posting(int $documentId, int $fieldId): ?Posting
    {
        return $this->byDocument[$documentId][$fieldId] ?? null;
    }

    public function contains(int $documentId): bool
    {
        return isset($this->byDocument[$documentId]);
    }

    public function termFrequency(int $documentId): int
    {
        $total = 0;

        foreach ($this->postings($documentId) as $posting) {
            $total += $posting->termFrequency;
        }

        return $total;
    }

    public function positions(int $documentId, int $fieldId): array
    {
        return $this->posting($documentId, $fieldId)?->positions() ?? [];
    }

    public function fields(int $documentId): array
    {
        return array_keys($this->postings($documentId));
    }
}
