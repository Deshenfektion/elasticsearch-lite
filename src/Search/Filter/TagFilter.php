<?php

declare(strict_types=1);

namespace EsLite\Search\Filter;

use EsLite\Support\Database\Dialect;
use EsLite\Support\Database\RowPlaceholders;

final readonly class TagFilter implements Filter
{
    public array $tags;

    public function __construct(array $tags, public bool $matchAll = false)
    {
        $this->tags = array_values(array_unique(array_filter(array_map(trim(...), $tags))));
    }

    public function name(): string
    {
        return 'tag';
    }

    public function compile(Dialect $dialect): CompiledFilter
    {
        if ($this->tags === []) {
            return new CompiledFilter('');
        }

        $placeholders = RowPlaceholders::list(count($this->tags));
        $bindings = $this->tags;

        $sql = sprintf(
            'd.id IN (SELECT dt.document_id FROM document_tags dt JOIN tags t ON t.id = dt.tag_id '
            . 'WHERE t.name IN (%s)',
            $placeholders,
        );

        if ($this->matchAll) {
            $sql .= ' GROUP BY dt.document_id HAVING COUNT(DISTINCT t.id) = ?';
            $bindings[] = count($this->tags);
        }

        return new CompiledFilter($sql . ')', $bindings);
    }

    public function toArray(): array
    {
        return ['tag' => $this->tags, 'match_all' => $this->matchAll];
    }
}
