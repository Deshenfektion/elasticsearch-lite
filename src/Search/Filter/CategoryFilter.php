<?php

declare(strict_types=1);

namespace EsLite\Search\Filter;

use EsLite\Support\Database\Dialect;
use EsLite\Support\Database\RowPlaceholders;

final readonly class CategoryFilter implements Filter
{
    public array $categories;

    public function __construct(array $categories)
    {
        $this->categories = array_values(array_unique(array_filter(array_map(trim(...), $categories))));
    }

    public function name(): string
    {
        return 'category';
    }

    public function compile(Dialect $dialect): CompiledFilter
    {
        if ($this->categories === []) {
            return new CompiledFilter('');
        }

        return new CompiledFilter(
            sprintf(
                'd.category_id IN (SELECT id FROM categories WHERE name IN (%s))',
                RowPlaceholders::list(count($this->categories)),
            ),
            $this->categories,
        );
    }

    public function toArray(): array
    {
        return ['category' => $this->categories];
    }
}
