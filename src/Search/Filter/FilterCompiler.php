<?php

declare(strict_types=1);

namespace EsLite\Search\Filter;

use EsLite\Repository\DocumentRepository;
use EsLite\Support\Database\Connection;

final class FilterCompiler
{
    public function __construct(
        private readonly Connection $connection,
        private readonly DocumentRepository $documents,
        private readonly int $limit = 20000,
    ) {
    }

    public function compile(FilterSet $filters): ?DocumentSet
    {
        if ($filters->isEmpty()) {
            return null;
        }

        $compiled = $filters->compile($this->connection->dialect());

        if ($compiled->where === '') {
            return null;
        }

        return new DocumentSet($this->documents->idsMatching($compiled->where, $compiled->bindings, $this->limit));
    }
}
