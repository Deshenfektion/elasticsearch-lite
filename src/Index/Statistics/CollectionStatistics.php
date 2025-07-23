<?php

declare(strict_types=1);

namespace EsLite\Index\Statistics;

final readonly class CollectionStatistics
{
    public function __construct(
        public int $documentCount,
        public array $fieldDocumentCounts,
        public array $fieldTotalLengths,
        public int $termCount = 0,
        public int $postingCount = 0,
        public int $tokenCount = 0,
    ) {
    }

    public static function empty(): self
    {
        return new self(0, [], []);
    }

    public function averageFieldLength(int $fieldId): float
    {
        $documents = $this->fieldDocumentCounts[$fieldId] ?? 0;

        if ($documents === 0) {
            return 0.0;
        }

        return ($this->fieldTotalLengths[$fieldId] ?? 0) / $documents;
    }

    public function fieldDocumentCount(int $fieldId): int
    {
        return $this->fieldDocumentCounts[$fieldId] ?? 0;
    }

    public function toArray(): array
    {
        $fields = [];

        foreach ($this->fieldDocumentCounts as $fieldId => $documents) {
            $fields[$fieldId] = [
                'documents' => $documents,
                'total_length' => $this->fieldTotalLengths[$fieldId] ?? 0,
                'average_length' => round($this->averageFieldLength($fieldId), 2),
            ];
        }

        return [
            'documents' => $this->documentCount,
            'terms' => $this->termCount,
            'postings' => $this->postingCount,
            'tokens' => $this->tokenCount,
            'fields' => $fields,
        ];
    }
}
