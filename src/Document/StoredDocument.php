<?php

declare(strict_types=1);

namespace EsLite\Document;

use DateTimeImmutable;

final readonly class StoredDocument
{
    public function __construct(
        public int $id,
        public string $externalId,
        public string $mediaType,
        public string $title,
        public string $body,
        public ?string $url,
        public ?string $author,
        public ?string $category,
        public array $tags,
        public ?DateTimeImmutable $publishedAt,
        public int $tokenCount,
        public string $checksum,
        public ?DateTimeImmutable $indexedAt,
    ) {
    }

    public function metadata(): DocumentMetadata
    {
        return new DocumentMetadata(
            $this->title,
            $this->url,
            $this->author,
            $this->category,
            $this->tags,
            $this->publishedAt,
        );
    }

    public function toParsed(): ParsedDocument
    {
        return new ParsedDocument($this->externalId, $this->mediaType, $this->title, $this->body, $this->metadata());
    }

    public function fields(): array
    {
        return [
            'title' => $this->title,
            'tags' => implode(' ', $this->tags),
            'body' => $this->body,
        ];
    }

    public function toArray(): array
    {
        return [
            'id' => $this->externalId,
            'media_type' => $this->mediaType,
            'title' => $this->title,
            'url' => $this->url,
            'author' => $this->author,
            'category' => $this->category,
            'tags' => $this->tags,
            'published_at' => $this->publishedAt?->format(DateTimeImmutable::ATOM),
            'token_count' => $this->tokenCount,
            'indexed_at' => $this->indexedAt?->format(DateTimeImmutable::ATOM),
        ];
    }
}
