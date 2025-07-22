<?php

declare(strict_types=1);

namespace EsLite\Repository;

use EsLite\Support\Database\Assignment;
use EsLite\Support\Database\Connection;
use EsLite\Support\Database\RowPlaceholders;

final class PostingRepository
{
    private const array COLUMNS = ['term_id', 'field_id', 'document_id', 'term_frequency', 'field_length', 'positions'];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function fetch(array $termIds, bool $withPositions = true): array
    {
        if ($termIds === []) {
            return [];
        }

        $columns = $withPositions
            ? 'term_id, field_id, document_id, term_frequency, field_length, positions'
            : 'term_id, field_id, document_id, term_frequency, field_length';

        $rows = [];

        foreach (array_chunk(array_values($termIds), 200) as $chunk) {
            $rows[] = $this->connection->select(
                sprintf(
                    'SELECT %s FROM postings WHERE term_id IN (%s) ORDER BY term_id, document_id',
                    $columns,
                    RowPlaceholders::list(count($chunk)),
                ),
                $chunk,
            );
        }

        return array_merge(...$rows);
    }

    public function fetchForDocument(int $documentId): array
    {
        return $this->connection->select(
            'SELECT p.term_id, p.field_id, p.term_frequency, t.term FROM postings p '
            . 'JOIN terms t ON t.id = p.term_id WHERE p.document_id = ?',
            [$documentId],
        );
    }

    public function insert(array $rows): int
    {
        if ($rows === []) {
            return 0;
        }

        return $this->connection->upsert('postings', self::COLUMNS, $rows, ['term_id', 'field_id', 'document_id'], [
            'term_frequency' => Assignment::Replace,
            'field_length' => Assignment::Replace,
            'positions' => Assignment::Replace,
        ]);
    }

    public function deleteForDocument(int $documentId): int
    {
        return $this->connection->execute('DELETE FROM postings WHERE document_id = ?', [$documentId]);
    }

    public function count(): int
    {
        return (int) $this->connection->selectValue('SELECT COUNT(*) FROM postings');
    }

    public function truncate(): void
    {
        $this->connection->execute('DELETE FROM postings');
    }
}
