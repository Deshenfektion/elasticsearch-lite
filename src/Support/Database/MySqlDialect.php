<?php

declare(strict_types=1);

namespace EsLite\Support\Database;

final class MySqlDialect implements Dialect
{
    private const string INCOMING_ALIAS = 'incoming';

    public function name(): string
    {
        return 'mysql';
    }

    public function quoteIdentifier(string $identifier): string
    {
        return '`' . str_replace('`', '``', $identifier) . '`';
    }

    public function insertOnConflict(
        string $table,
        array $columns,
        int $rowCount,
        array $conflictColumns,
        array $assignments,
    ): string {
        $sql = sprintf(
            'INSERT INTO %s (%s) VALUES %s AS %s ON DUPLICATE KEY UPDATE ',
            $this->quoteIdentifier($table),
            implode(', ', array_map($this->quoteIdentifier(...), $columns)),
            RowPlaceholders::build(count($columns), $rowCount),
            self::INCOMING_ALIAS,
        );

        if ($assignments === []) {
            $column = $conflictColumns[0] ?? 'id';

            return $sql . sprintf('%s = %s', $this->quoteIdentifier($column), $this->existing($table, $column));
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

        return $sql . implode(', ', $updates);
    }

    public function maxBindings(): int
    {
        return 8000;
    }

    public function supportsTransactionalSchema(): bool
    {
        return false;
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
