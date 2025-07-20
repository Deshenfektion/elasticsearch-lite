<?php

declare(strict_types=1);

namespace EsLite\Index;

use EsLite\Index\Codec\VarIntCodec;

final class Posting
{
    private ?array $positions = null;

    public function __construct(
        public readonly int $documentId,
        public readonly int $fieldId,
        public readonly int $termFrequency,
        public readonly int $fieldLength,
        private readonly ?string $positionsBlob = null,
    ) {
    }

    public static function fromRow(array $row): self
    {
        $positions = $row['positions'] ?? null;

        if (is_resource($positions)) {
            $positions = (string) stream_get_contents($positions);
        }

        return new self(
            (int) $row['document_id'],
            (int) $row['field_id'],
            (int) $row['term_frequency'],
            (int) $row['field_length'],
            $positions === null ? null : (string) $positions,
        );
    }

    public function positions(): array
    {
        return $this->positions ??= $this->positionsBlob === null
            ? []
            : VarIntCodec::decodeSorted($this->positionsBlob);
    }

    public function hasPositions(): bool
    {
        return $this->positionsBlob !== null && $this->positionsBlob !== '';
    }
}
