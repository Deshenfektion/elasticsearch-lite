<?php

declare(strict_types=1);

namespace EsLite\Support\Database;

interface Dialect
{
    public function name(): string;

    public function quoteIdentifier(string $identifier): string;

    public function insertOnConflict(
        string $table,
        array $columns,
        int $rowCount,
        array $conflictColumns,
        array $assignments,
    ): string;

    public function maxBindings(): int;

    public function supportsTransactionalSchema(): bool;
}
