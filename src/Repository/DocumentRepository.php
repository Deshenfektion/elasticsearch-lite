<?php

declare(strict_types=1);

namespace EsLite\Repository;

use DateTimeImmutable;
use DateTimeZone;
use EsLite\Document\ParsedDocument;
use EsLite\Document\StoredDocument;
use EsLite\Support\Clock;
use EsLite\Support\Database\Connection;
use EsLite\Support\Database\RowPlaceholders;
use EsLite\Support\SystemClock;

final class DocumentRepository
{
    private const string SELECT = 'SELECT d.id, d.external_id, d.media_type, d.title, d.body, d.url, d.author, '
        . 'c.name AS category, d.published_at, d.token_count, d.checksum, d.indexed_at '
        . 'FROM documents d LEFT JOIN categories c ON c.id = d.category_id';

    public function __construct(
        private readonly Connection $connection,
        private readonly Clock $clock = new SystemClock(),
    ) {
    }

    public function findByExternalId(string $externalId): ?StoredDocument
    {
        $row = $this->connection->selectOne(self::SELECT . ' WHERE d.external_id = ?', [$externalId]);

        return $row === null ? null : $this->hydrate($row, $this->tagsFor([(int) $row['id']]));
    }

    public function findById(int $id): ?StoredDocument
    {
        $row = $this->connection->selectOne(self::SELECT . ' WHERE d.id = ?', [$id]);

        return $row === null ? null : $this->hydrate($row, $this->tagsFor([$id]));
    }

    public function findMany(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        $rows = $this->connection->select(
            self::SELECT . sprintf(' WHERE d.id IN (%s)', RowPlaceholders::list(count($ids))),
            array_values($ids),
        );

        $tags = $this->tagsFor($ids);
        $documents = [];

        foreach ($rows as $row) {
            $documents[(int) $row['id']] = $this->hydrate($row, $tags);
        }

        return $documents;
    }

    public function save(ParsedDocument $document, ?int $categoryId, int $tokenCount, ?int $existingId): int
    {
        $now = $this->clock->now()->format('Y-m-d H:i:s');
        $published = $document->metadata->publishedAt?->format('Y-m-d H:i:s');

        if ($existingId !== null) {
            $this->connection->execute(
                'UPDATE documents SET media_type = ?, title = ?, body = ?, url = ?, author = ?, category_id = ?, '
                . 'published_at = ?, checksum = ?, token_count = ?, indexed_at = ?, updated_at = ? WHERE id = ?',
                [
                    $document->mediaType,
                    $document->title,
                    $document->body,
                    $document->metadata->url,
                    $document->metadata->author,
                    $categoryId,
                    $published,
                    $document->checksum(),
                    $tokenCount,
                    $now,
                    $now,
                    $existingId,
                ],
            );

            return $existingId;
        }

        return $this->connection->insert(
            'INSERT INTO documents (external_id, media_type, title, body, url, author, category_id, published_at, '
            . 'checksum, token_count, indexed_at, created_at, updated_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $document->externalId,
                $document->mediaType,
                $document->title,
                $document->body,
                $document->metadata->url,
                $document->metadata->author,
                $categoryId,
                $published,
                $document->checksum(),
                $tokenCount,
                $now,
                $now,
                $now,
            ],
        );
    }

    public function delete(int $id): bool
    {
        return $this->connection->execute('DELETE FROM documents WHERE id = ?', [$id]) > 0;
    }

    public function count(): int
    {
        return (int) $this->connection->selectValue('SELECT COUNT(*) FROM documents');
    }

    public function idsMatching(string $where, array $bindings, int $limit): array
    {
        $sql = 'SELECT d.id FROM documents d';

        if ($where !== '') {
            $sql .= ' WHERE ' . $where;
        }

        $sql .= ' ORDER BY d.id LIMIT ' . max(1, $limit);

        return array_map(intval(...), $this->connection->selectColumn($sql, $bindings));
    }

    public function orderByPublished(array $documentIds, int $from, int $size, string $direction = 'DESC'): array
    {
        if ($documentIds === []) {
            return [];
        }

        $direction = strtoupper($direction) === 'ASC' ? 'ASC' : 'DESC';
        $nullOrder = $direction === 'ASC' ? 'DESC' : 'ASC';

        $sql = sprintf(
            'SELECT id FROM documents WHERE id IN (%s) '
            . 'ORDER BY CASE WHEN published_at IS NULL THEN 1 ELSE 0 END %s, published_at %s, id %s '
            . 'LIMIT %d OFFSET %d',
            RowPlaceholders::list(count($documentIds)),
            $nullOrder,
            $direction,
            $direction,
            max(1, $size),
            max(0, $from),
        );

        return array_map(intval(...), $this->connection->selectColumn($sql, array_values($documentIds)));
    }

    public function chunkIds(int $size, callable $callback): void
    {
        $lastId = 0;

        while (true) {
            $ids = array_map(intval(...), $this->connection->selectColumn(
                'SELECT id FROM documents WHERE id > ? ORDER BY id LIMIT ' . max(1, $size),
                [$lastId],
            ));

            if ($ids === []) {
                return;
            }

            $callback($ids);
            $lastId = (int) end($ids);
        }
    }

    public function authors(int $limit = 50): array
    {
        return $this->connection->selectPairs(
            'SELECT author, COUNT(*) AS total FROM documents WHERE author IS NOT NULL '
            . 'GROUP BY author ORDER BY total DESC, author ASC LIMIT ' . max(1, $limit),
        );
    }

    public function publishedRange(): array
    {
        $row = $this->connection->selectOne(
            'SELECT MIN(published_at) AS earliest, MAX(published_at) AS latest FROM documents',
        );

        return [
            'earliest' => $row['earliest'] ?? null,
            'latest' => $row['latest'] ?? null,
        ];
    }

    public function truncate(): void
    {
        $this->connection->execute('DELETE FROM documents');
    }

    private function tagsFor(array $documentIds): array
    {
        if ($documentIds === []) {
            return [];
        }

        $rows = $this->connection->select(
            sprintf(
                'SELECT dt.document_id, t.name FROM document_tags dt JOIN tags t ON t.id = dt.tag_id '
                . 'WHERE dt.document_id IN (%s) ORDER BY t.name',
                RowPlaceholders::list(count($documentIds)),
            ),
            array_values($documentIds),
        );

        $tags = [];

        foreach ($rows as $row) {
            $tags[(int) $row['document_id']][] = (string) $row['name'];
        }

        return $tags;
    }

    private function hydrate(array $row, array $tags): StoredDocument
    {
        $id = (int) $row['id'];

        return new StoredDocument(
            $id,
            (string) $row['external_id'],
            (string) $row['media_type'],
            (string) $row['title'],
            (string) $row['body'],
            $row['url'] === null ? null : (string) $row['url'],
            $row['author'] === null ? null : (string) $row['author'],
            $row['category'] === null ? null : (string) $row['category'],
            $tags[$id] ?? [],
            $this->date($row['published_at'] ?? null),
            (int) $row['token_count'],
            (string) $row['checksum'],
            $this->date($row['indexed_at'] ?? null),
        );
    }

    private function date(mixed $value): ?DateTimeImmutable
    {
        if ($value === null || $value === '') {
            return null;
        }

        return new DateTimeImmutable((string) $value, new DateTimeZone('UTC'));
    }
}
