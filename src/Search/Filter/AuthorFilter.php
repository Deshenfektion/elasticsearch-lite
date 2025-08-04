<?php

declare(strict_types=1);

namespace EsLite\Search\Filter;

use EsLite\Support\Database\Dialect;
use EsLite\Support\Database\RowPlaceholders;

final readonly class AuthorFilter implements Filter
{
    public array $authors;

    public function __construct(array $authors)
    {
        $this->authors = array_values(array_unique(array_filter(array_map(trim(...), $authors))));
    }

    public function name(): string
    {
        return 'author';
    }

    public function compile(Dialect $dialect): CompiledFilter
    {
        if ($this->authors === []) {
            return new CompiledFilter('');
        }

        return new CompiledFilter(
            sprintf('d.author IN (%s)', RowPlaceholders::list(count($this->authors))),
            $this->authors,
        );
    }

    public function toArray(): array
    {
        return ['author' => $this->authors];
    }
}
