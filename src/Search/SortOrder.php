<?php

declare(strict_types=1);

namespace EsLite\Search;

enum SortOrder: string
{
    case Relevance = 'relevance';
    case Newest = 'newest';
    case Oldest = 'oldest';

    public static function fromString(?string $value): self
    {
        return match (strtolower((string) $value)) {
            'newest', 'date', 'date_desc' => self::Newest,
            'oldest', 'date_asc' => self::Oldest,
            default => self::Relevance,
        };
    }

    public function direction(): string
    {
        return $this === self::Oldest ? 'ASC' : 'DESC';
    }
}
