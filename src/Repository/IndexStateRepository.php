<?php

declare(strict_types=1);

namespace EsLite\Repository;

use EsLite\Support\Database\Assignment;
use EsLite\Support\Database\Connection;

final class IndexStateRepository
{
    public const string DOCUMENT_COUNT = 'document_count';

    public function __construct(private readonly Connection $connection)
    {
    }

    public function all(): array
    {
        $state = [];

        foreach ($this->connection->selectPairs('SELECT stat_key, stat_value FROM index_state') as $key => $value) {
            $state[(string) $key] = (int) $value;
        }

        return $state;
    }

    public function get(string $key): int
    {
        return (int) $this->connection->selectValue(
            'SELECT stat_value FROM index_state WHERE stat_key = ?',
            [$key],
        );
    }

    public function apply(array $deltas): void
    {
        $rows = [];

        foreach ($deltas as $key => $delta) {
            if ($delta === 0) {
                continue;
            }

            $rows[] = ['stat_key' => (string) $key, 'stat_value' => $delta];
        }

        if ($rows === []) {
            return;
        }

        $this->connection->upsert('index_state', ['stat_key', 'stat_value'], $rows, ['stat_key'], [
            'stat_value' => Assignment::Increment,
        ]);
    }

    public function reset(): void
    {
        $this->connection->execute('DELETE FROM index_state');
    }

    public static function fieldDocumentKey(int $fieldId): string
    {
        return sprintf('field_%d_documents', $fieldId);
    }

    public static function fieldLengthKey(int $fieldId): string
    {
        return sprintf('field_%d_length', $fieldId);
    }
}
