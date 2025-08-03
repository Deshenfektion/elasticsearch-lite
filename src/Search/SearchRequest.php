<?php

declare(strict_types=1);

namespace EsLite\Search;

use EsLite\Search\Filter\FilterSet;
use EsLite\Support\Config;

final readonly class SearchRequest
{
    public function __construct(
        public string $query,
        public FilterSet $filters = new FilterSet(),
        public int $from = 0,
        public int $size = 10,
        public bool $highlight = true,
        public bool $explain = false,
        public bool $facets = true,
        public SortOrder $sort = SortOrder::Relevance,
    ) {
    }

    public static function fromArray(array $parameters, Config $config): self
    {
        $maxSize = $config->int('app.search.max_size', 100);
        $size = self::integer($parameters['size'] ?? null, $config->int('app.search.default_size', 10));
        $size = max(1, min($size, $maxSize));
        $from = self::integer($parameters['from'] ?? null, 0);

        if (isset($parameters['page'])) {
            $from = max(0, (self::integer($parameters['page'], 1) - 1) * $size);
        }

        return new self(
            trim((string) ($parameters['q'] ?? $parameters['query'] ?? '')),
            FilterSet::fromArray($parameters),
            max(0, $from),
            $size,
            self::flag($parameters['highlight'] ?? true),
            self::flag($parameters['explain'] ?? false),
            self::flag($parameters['facets'] ?? true),
            SortOrder::fromString($parameters['sort'] ?? null),
        );
    }

    public function page(): int
    {
        return intdiv($this->from, max($this->size, 1)) + 1;
    }

    public function signature(): string
    {
        return sha1(implode('|', [
            $this->query,
            $this->filters->signature(),
            $this->from,
            $this->size,
            $this->highlight ? '1' : '0',
            $this->explain ? '1' : '0',
            $this->sort->value,
        ]));
    }

    private static function integer(mixed $value, int $default): int
    {
        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        return $default;
    }

    private static function flag(mixed $value): bool
    {
        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower((string) $value), ['1', 'true', 'yes', 'on'], true);
    }
}
