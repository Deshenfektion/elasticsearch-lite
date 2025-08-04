<?php

declare(strict_types=1);

namespace EsLite\Search\Filter;

use DateTimeImmutable;
use EsLite\Support\Database\Dialect;
use Throwable;

final class FilterSet
{
    private array $filters = [];

    public function __construct(Filter ...$filters)
    {
        foreach ($filters as $filter) {
            $this->add($filter);
        }
    }

    public static function fromArray(array $parameters): self
    {
        $set = new self();
        $categories = self::values($parameters, ['category', 'categories']);
        $authors = self::values($parameters, ['author', 'authors']);
        $tags = self::values($parameters, ['tag', 'tags']);

        if ($categories !== []) {
            $set->add(new CategoryFilter($categories));
        }

        if ($authors !== []) {
            $set->add(new AuthorFilter($authors));
        }

        if ($tags !== []) {
            $set->add(new TagFilter($tags, self::flag($parameters, 'match_all_tags')));
        }

        $from = self::date($parameters['from'] ?? $parameters['published_from'] ?? null);
        $to = self::date($parameters['to'] ?? $parameters['published_to'] ?? null);

        if ($from !== null || $to !== null) {
            $set->add(new DateRangeFilter($from, $to));
        }

        return $set;
    }

    public function add(Filter $filter): void
    {
        $this->filters[] = $filter;
    }

    public function isEmpty(): bool
    {
        return $this->filters === [];
    }

    public function filters(): array
    {
        return $this->filters;
    }

    public function compile(Dialect $dialect): CompiledFilter
    {
        return CompiledFilter::combine(array_map(
            static fn (Filter $filter): CompiledFilter => $filter->compile($dialect),
            $this->filters,
        ));
    }

    public function toArray(): array
    {
        $description = [];

        foreach ($this->filters as $filter) {
            $description = array_merge($description, $filter->toArray());
        }

        return $description;
    }

    public function signature(): string
    {
        $description = $this->toArray();
        ksort($description);

        return (string) json_encode($description);
    }

    private static function values(array $parameters, array $keys): array
    {
        foreach ($keys as $key) {
            $value = $parameters[$key] ?? null;

            if (is_string($value) && trim($value) !== '') {
                return array_map(trim(...), explode(',', $value));
            }

            if (is_array($value) && $value !== []) {
                return array_values(array_filter(array_map(
                    static fn (mixed $item): string => is_scalar($item) ? trim((string) $item) : '',
                    $value,
                )));
            }
        }

        return [];
    }

    private static function flag(array $parameters, string $key): bool
    {
        $value = $parameters[$key] ?? false;

        return $value === true || in_array((string) $value, ['1', 'true', 'yes'], true);
    }

    private static function date(mixed $value): ?DateTimeImmutable
    {
        if (!is_string($value) || trim($value) === '') {
            return null;
        }

        try {
            return new DateTimeImmutable($value);
        } catch (Throwable) {
            return null;
        }
    }
}
