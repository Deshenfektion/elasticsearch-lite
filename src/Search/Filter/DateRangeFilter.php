<?php

declare(strict_types=1);

namespace EsLite\Search\Filter;

use DateTimeImmutable;
use EsLite\Support\Database\Dialect;

final readonly class DateRangeFilter implements Filter
{
    public function __construct(
        public ?DateTimeImmutable $from = null,
        public ?DateTimeImmutable $to = null,
    ) {
    }

    public function name(): string
    {
        return 'published';
    }

    public function compile(Dialect $dialect): CompiledFilter
    {
        $fragments = [];
        $bindings = [];

        if ($this->from !== null) {
            $fragments[] = 'd.published_at >= ?';
            $bindings[] = $this->from->format('Y-m-d H:i:s');
        }

        if ($this->to !== null) {
            $fragments[] = 'd.published_at <= ?';
            $bindings[] = $this->to->format('Y-m-d H:i:s');
        }

        return new CompiledFilter(implode(' AND ', $fragments), $bindings);
    }

    public function toArray(): array
    {
        return [
            'published_from' => $this->from?->format('Y-m-d'),
            'published_to' => $this->to?->format('Y-m-d'),
        ];
    }
}
