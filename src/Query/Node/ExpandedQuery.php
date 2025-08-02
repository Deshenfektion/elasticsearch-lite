<?php

declare(strict_types=1);

namespace EsLite\Query\Node;

final readonly class ExpandedQuery implements Query
{
    public array $terms;

    public function __construct(
        array $terms,
        public ?string $field = null,
        public string $source = '',
        public bool $truncated = false,
    ) {
        $this->terms = array_values($terms);
    }

    public function kind(): string
    {
        return 'expanded';
    }

    public function field(): ?string
    {
        return $this->field;
    }

    public function describe(): string
    {
        return sprintf(
            '%s%s -> (%s)',
            $this->field === null ? '' : $this->field . ':',
            $this->source,
            implode(' ', $this->terms),
        );
    }

    public function toArray(): array
    {
        return [
            'type' => 'expanded',
            'field' => $this->field,
            'source' => $this->source,
            'terms' => $this->terms,
            'truncated' => $this->truncated,
        ];
    }
}
