<?php

declare(strict_types=1);

namespace EsLite\Support\Database;

final class SqliteDialect implements Dialect
{
    private const string INCOMING_ALIAS = 'excluded';

    public function name(): string
    {
        return 'sqlite';
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '"' . str_replace('"', '""', $identifier) . '"';
    }

    public function insertOnConflict(
        string $table,
        array $columns,
        int $rowCount,
        array $conflictColumns,
        array $assignments,
    ): string {
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s ON CONFLICT (%s) ',
            $this->quoteIdentifier($table),
            implode(', ', array_map($this->quoteIdentifier(...), $columns)),
            RowPlaceholders::build(count($columns), $rowCount),
            implode(', ', array_map($this->quoteIdentifier(...), $conflictColumns)),
        );

        if ($assignments === []) {
            return $sql . 'DO NOTHING';
        }

        $updates = [];

        foreach ($assignments as $column => $assignment) {
            $updates[] = sprintf(
                '%s = %s',
                $this->quoteIdentifier($column),
                $assignment === Assignment::Increment
                    ? sprintf('%s + %s', $this->existing($table, $column), $this->incoming($column))
                    : $this->incoming($column),
            );
        }

        return $sql . 'DO UPDATE SET ' . implode(', ', $updates);
    }

    public function maxBindings(): int
    {
        return 900;
    }

    public function supportsTransactionalSchema(): bool
    {
        return true;
    }

    private function incoming(string $column): string
    {
        return self::INCOMING_ALIAS . '.' . $this->quoteIdentifier($column);
    }

    private function existing(string $table, string $column): string
    {
        return $this->quoteIdentifier($table) . '.' . $this->quoteIdentifier($column);
    }
}
