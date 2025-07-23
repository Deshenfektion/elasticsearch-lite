<?php

declare(strict_types=1);

namespace EsLite\Index;

final readonly class IndexingResult
{
    public function __construct(
        public int $documentId,
        public string $externalId,
        public IndexingStatus $status,
        public int $tokenCount = 0,
        public int $termCount = 0,
        public int $tookMicros = 0,
    ) {
    }

    public function withTiming(int $tookMicros): self
    {
        return new self(
            $this->documentId,
            $this->externalId,
            $this->status,
            $this->tokenCount,
            $this->termCount,
            $tookMicros,
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->externalId,
            'status' => $this->status->value,
            'tokens' => $this->tokenCount,
            'terms' => $this->termCount,
            'took_ms' => round($this->tookMicros / 1000, 3),
        ];
    }
}
