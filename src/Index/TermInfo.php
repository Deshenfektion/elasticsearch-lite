<?php

declare(strict_types=1);

namespace EsLite\Index;

final readonly class TermInfo
{
    public function __construct(
        public int $id,
        public string $term,
        public int $documentFrequency,
        public int $totalFrequency,
    ) {
    }

    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (string) $row['term'],
            (int) $row['document_frequency'],
            (int) $row['total_frequency'],
        );
    }

    public function toArray(): array
    {
        return [
            'term' => $this->term,
            'document_frequency' => $this->documentFrequency,
            'total_frequency' => $this->totalFrequency,
        ];
    }
}
