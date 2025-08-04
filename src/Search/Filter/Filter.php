<?php

declare(strict_types=1);

namespace EsLite\Search\Filter;

use EsLite\Support\Database\Dialect;

interface Filter
{
    public function name(): string;

    public function compile(Dialect $dialect): CompiledFilter;

    public function toArray(): array;
}
