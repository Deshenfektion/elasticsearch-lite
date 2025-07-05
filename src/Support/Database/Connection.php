<?php

declare(strict_types=1);

namespace EsLite\Support\Database;

use DateTimeInterface;
use EsLite\Exception\StorageException;
use PDO;
use PDOException;
use PDOStatement;
use Throwable;

final class Connection
{
    private array $statements = [];

    private int $queryCount = 0;

    public function __construct(
        private readonly PDO $pdo,
        private readonly Dialect $dialect,
    ) {
    }

    public function dialect(): Dialect
    {
        return $this->dialect;
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function queryCount(): int
    {
        return $this->queryCount;
    }

    public function select(string $sql, array $bindings = []): array
    {
        return $this->run($sql, $bindings)->fetchAll(PDO::FETCH_ASSOC);
    }

    public function selectOne(string $sql, array $bindings = []): ?array
    {
        $row = $this->run($sql, $bindings)->fetch(PDO::FETCH_ASSOC);

        return $row === false ? null : $row;
    }

    public function selectColumn(string $sql, array $bindings = []): array
    {
        return $this->run($sql, $bindings)->fetchAll(PDO::FETCH_COLUMN);
    }

    public function selectValue(string $sql, array $bindings = []): mixed
    {
        $value = $this->run($sql, $bindings)->fetchColumn();

        return $value === false ? null : $value;
    }

    public function selectPairs(string $sql, array $bindings = []): array
    {
        $pairs = [];

        foreach ($this->run($sql, $bindings)->fetchAll(PDO::FETCH_NUM) as $row) {
            $pairs[$row[0]] = $row[1];
        }

        return $pairs;
    }

    public function execute(string $sql, array $bindings = []): int
    {
        return $this->run($sql, $bindings)->rowCount();
    }

    public function insert(string $sql, array $bindings = []): int
    {
        $this->run($sql, $bindings);

        return (int) $this->pdo->lastInsertId();
    }

    public function raw(string $sql): void
    {
        try {
            $this->queryCount++;
            $this->pdo->exec($sql);
        } catch (PDOException $exception) {
            throw StorageException::queryFailed($sql, $exception);
        }
    }

    public function upsert(
        string $table,
        array $columns,
        array $rows,
        array $conflictColumns,
        array $assignments = [],
    ): int {
        if ($rows === []) {
            return 0;
        }

        $perRow = count($columns);
        $chunkSize = max(1, intdiv($this->dialect->maxBindings(), $perRow));
        $affected = 0;

        foreach (array_chunk($rows, $chunkSize) as $chunk) {
            $sql = $this->dialect->insertOnConflict($table, $columns, count($chunk), $conflictColumns, $assignments);
            $bindings = [];

            foreach ($chunk as $row) {
                foreach ($columns as $column) {
                    $bindings[] = $row[$column] ?? null;
                }
            }

            $affected += $this->execute($sql, $bindings);
        }

        return $affected;
    }

    public function transaction(callable $callback): mixed
    {
        if ($this->pdo->inTransaction()) {
            return $callback($this);
        }

        $this->pdo->beginTransaction();

        try {
            $result = $callback($this);
            $this->pdo->commit();

            return $result;
        } catch (Throwable $exception) {
            $this->pdo->rollBack();

            throw $exception;
        }
    }

    private function run(string $sql, array $bindings): PDOStatement
    {
        $statement = $this->statements[$sql] ??= $this->prepare($sql);
        $this->queryCount++;

        try {
            $statement->closeCursor();
            $statement->execute($this->normaliseBindings($bindings));
        } catch (PDOException $exception) {
            throw StorageException::queryFailed($sql, $exception);
        }

        return $statement;
    }

    private function prepare(string $sql): PDOStatement
    {
        try {
            return $this->pdo->prepare($sql);
        } catch (PDOException $exception) {
            throw StorageException::queryFailed($sql, $exception);
        }
    }

    private function normaliseBindings(array $bindings): array
    {
        $normalised = [];

        foreach ($bindings as $key => $value) {
            $value = match (true) {
                is_bool($value) => $value ? 1 : 0,
                $value instanceof DateTimeInterface => $value->format('Y-m-d H:i:s'),
                default => $value,
            };

            if (is_int($key)) {
                $normalised[] = $value;

                continue;
            }

            $normalised[$key] = $value;
        }

        return $normalised;
    }
}
