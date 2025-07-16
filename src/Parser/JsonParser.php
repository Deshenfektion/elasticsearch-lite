<?php

declare(strict_types=1);

namespace EsLite\Parser;

use DateTimeImmutable;
use EsLite\Document\DocumentMetadata;
use EsLite\Document\ParsedDocument;
use EsLite\Document\SourceDocument;
use EsLite\Parser\Exception\ParseException;
use JsonException;
use Throwable;

final class JsonParser implements Parser
{
    private const array DEFAULT_MAPPING = [
        'title' => ['title', 'name', 'headline'],
        'body' => ['body', 'content', 'text', 'description', 'abstract'],
        'url' => ['url', 'link', 'permalink'],
        'author' => ['author', 'creator', 'by'],
        'category' => ['category', 'section', 'type'],
        'tags' => ['tags', 'keywords', 'labels'],
        'published_at' => ['published_at', 'publishedAt', 'date', 'created_at'],
    ];

    private array $mapping;

    public function __construct(array $mapping = [])
    {
        $this->mapping = array_merge(self::DEFAULT_MAPPING, $mapping);
    }

    public function mediaTypes(): array
    {
        return ['application/json'];
    }

    public function parse(SourceDocument $document): ParsedDocument
    {
        $payload = $this->decode($document->content, $document->mediaType);
        $title = $this->firstString($payload, $this->mapping['title']);
        $body = $this->firstString($payload, $this->mapping['body']) ?? $this->flatten($payload);

        if (trim($body) === '') {
            throw ParseException::emptyContent($document->mediaType);
        }

        $metadata = new DocumentMetadata(
            $title,
            $this->firstString($payload, $this->mapping['url']),
            $this->firstString($payload, $this->mapping['author']),
            $this->firstString($payload, $this->mapping['category']),
            $this->tags($payload),
            $this->date($payload),
        );

        $metadata = $metadata->merge($document->metadata);

        return new ParsedDocument(
            $document->externalId,
            MediaType::normalise($document->mediaType),
            $metadata->title ?? Text::truncate(Text::firstLine(Text::normaliseWhitespace($body)), 120),
            Text::normaliseWhitespace($body),
            $metadata,
        );
    }

    private function decode(string $content, string $mediaType): array
    {
        try {
            $payload = json_decode($content, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw ParseException::malformed($mediaType, $exception->getMessage());
        }

        if (!is_array($payload)) {
            throw ParseException::malformed($mediaType, 'expected an object or array at the root');
        }

        return $payload;
    }

    private function firstString(array $payload, array $keys): ?string
    {
        foreach ($keys as $key) {
            $value = $this->lookup($payload, $key);

            if (is_string($value) && trim($value) !== '') {
                return trim($value);
            }

            if (is_int($value) || is_float($value)) {
                return (string) $value;
            }
        }

        return null;
    }

    private function lookup(array $payload, string $path): mixed
    {
        $cursor = $payload;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return null;
            }

            $cursor = $cursor[$segment];
        }

        return $cursor;
    }

    private function tags(array $payload): array
    {
        foreach ($this->mapping['tags'] as $key) {
            $value = $this->lookup($payload, $key);

            if (is_array($value)) {
                return array_values(array_filter(
                    array_map(static fn (mixed $tag): string => is_scalar($tag) ? trim((string) $tag) : '', $value),
                    static fn (string $tag): bool => $tag !== '',
                ));
            }

            if (is_string($value) && trim($value) !== '') {
                return array_map(trim(...), explode(',', $value));
            }
        }

        return [];
    }

    private function date(array $payload): ?DateTimeImmutable
    {
        $value = $this->firstString($payload, $this->mapping['published_at']);

        if ($value === null) {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }

    private function flatten(array $payload, int $depth = 0): string
    {
        if ($depth > 8) {
            return '';
        }

        $parts = [];

        foreach ($payload as $value) {
            if (is_string($value)) {
                $parts[] = $value;
            } elseif (is_int($value) || is_float($value)) {
                $parts[] = (string) $value;
            } elseif (is_array($value)) {
                $parts[] = $this->flatten($value, $depth + 1);
            }
        }

        return trim(implode("\n", array_filter($parts, static fn (string $part): bool => trim($part) !== '')));
    }
}
