<?php

declare(strict_types=1);

namespace EsLite\Document;

use DateTimeImmutable;

final readonly class DocumentMetadata
{
    public array $tags;

    public function __construct(
        public ?string $title = null,
        public ?string $url = null,
        public ?string $author = null,
        public ?string $category = null,
        array $tags = [],
        public ?DateTimeImmutable $publishedAt = null,
    ) {
        $this->tags = array_values(array_unique(array_filter(
            array_map(static fn (mixed $tag): string => trim((string) $tag), $tags),
            static fn (string $tag): bool => $tag !== '',
        )));
    }

    public function merge(self $other): self
    {
        return new self(
            $other->title ?? $this->title,
            $other->url ?? $this->url,
            $other->author ?? $this->author,
            $other->category ?? $this->category,
            $other->tags === [] ? $this->tags : $other->tags,
            $other->publishedAt ?? $this->publishedAt,
        );
    }

    public function toArray(): array
    {
        return [
            'title' => $this->title,
            'url' => $this->url,
            'author' => $this->author,
            'category' => $this->category,
            'tags' => $this->tags,
            'published_at' => $this->publishedAt?->format(DateTimeImmutable::ATOM),
        ];
    }
}
