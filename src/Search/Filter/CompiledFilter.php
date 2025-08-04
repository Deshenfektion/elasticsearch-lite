<?php

declare(strict_types=1);

namespace EsLite\Search\Filter;

final readonly class CompiledFilter
{
    public function __construct(
        public string $where,
        public array $bindings = [],
    ) {
    }

    public static function combine(array $filters): self
    {
        $fragments = [];
        $bindings = [];

        foreach ($filters as $filter) {
            if ($filter->where === '') {
                continue;
            }

            $fragments[] = '(' . $filter->where . ')';
            $bindings = array_merge($bindings, $filter->bindings);
        }

        return new self(implode(' AND ', $fragments), $bindings);
    }
}
