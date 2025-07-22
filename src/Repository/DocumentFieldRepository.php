<?php

declare(strict_types=1);

namespace EsLite\Repository;

use EsLite\Support\Database\Assignment;
use EsLite\Support\Database\Connection;

final class DocumentFieldRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function lengths(int $documentId): array
    {
        $lengths = [];

        foreach ($this->connection->selectPairs(
            'SELECT field_id, length FROM document_fields WHERE document_id = ?',
            [$documentId],
        ) as $fieldId => $length) {
            $lengths[(int) $fieldId] = (int) $length;
        }

        return $lengths;
    }

    public function replace(int $documentId, array $lengths): void
    {
        $this->connection->execute('DELETE FROM document_fields WHERE document_id = ?', [$documentId]);

        if ($lengths === []) {
            return;
        }

        $rows = [];

        foreach ($lengths as $fieldId => $length) {
            $rows[] = ['document_id' => $documentId, 'field_id' => (int) $fieldId, 'length' => (int) $length];
        }

        $this->connection->upsert(
            'document_fields',
            ['document_id', 'field_id', 'length'],
            $rows,
            ['document_id', 'field_id'],
            ['length' => Assignment::Replace],
        );
    }

    public function deleteForDocument(int $documentId): void
    {
        $this->connection->execute('DELETE FROM document_fields WHERE document_id = ?', [$documentId]);
    }

    public function truncate(): void
    {
        $this->connection->execute('DELETE FROM document_fields');
    }
}
