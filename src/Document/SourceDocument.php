<?php

declare(strict_types=1);

namespace EsLite\Document;

use DateTimeImmutable;
use Throwable;

final readonly class SourceDocument
{
    public function __construct(
        public string $externalId,
        public string $mediaType,
        public string $content,
        public DocumentMetadata $metadata = new DocumentMetadata(),
    ) {
    }

    public static function fromArray(array $payload): self
    {
        $externalId = trim((string) ($payload['id'] ?? $payload['external_id'] ?? ''));

        if ($externalId === '') {
            throw InvalidDocument::missingField('id');
        }

        if (mb_strlen($externalId) > 191) {
            throw InvalidDocument::tooLong('id', 191);
        }

        $content = $payload['content'] ?? $payload['body'] ?? null;

        if (is_array($content)) {
            $content = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (!is_string($content) || trim($content) === '') {
            throw InvalidDocument::missingField('content');
        }

        return new self(
            $externalId,
            (string) ($payload['media_type'] ?? $payload['type'] ?? 'text/plain'),
            $content,
            self::metadata($payload),
        );
    }

    public function withContent(string $content): self
    {
        return new self($this->externalId, $this->mediaType, $content, $this->metadata);
    }

    private static function metadata(array $payload): DocumentMetadata
    {
        $tags = $payload['tags'] ?? [];

        if (is_string($tags)) {
            $tags = array_map(trim(...), explode(',', $tags));
        }

        if (!is_array($tags)) {
            throw InvalidDocument::invalidField('tags', 'a list of strings');
        }

        return new DocumentMetadata(
            self::optionalString($payload, 'title'),
            self::optionalString($payload, 'url'),
            self::optionalString($payload, 'author'),
            self::optionalString($payload, 'category'),
            $tags,
            self::date($payload['published_at'] ?? null),
        );
    }

    private static function optionalString(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        if ($value === null) {
            return null;
        }

        if (!is_string($value) && !is_numeric($value)) {
            throw InvalidDocument::invalidField($key, 'a string');
        }

        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    private static function date(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return new DateTimeImmutable((string) $value);
        } catch (Throwable) {
            throw InvalidDocument::invalidField('published_at', 'a parsable date');
        }
    }
}
