<?php

declare(strict_types=1);

namespace EsLite\Document;

final readonly class ParsedDocument
{
    public function __construct(
        public string $externalId,
        public string $mediaType,
        public string $title,
        public string $body,
        public DocumentMetadata $metadata,
    ) {
    }

    public function fields(): array
    {
        return [
            'title' => $this->title,
            'tags' => implode(' ', $this->metadata->tags),
            'body' => $this->body,
        ];
    }

    public function checksum(): string
    {
        return sha1(implode("\x1f", [
            $this->mediaType,
            $this->title,
            $this->body,
            (string) json_encode($this->metadata->toArray()),
        ]));
    }
}
