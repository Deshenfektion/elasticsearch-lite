<?php

declare(strict_types=1);

namespace EsLite\Repository;

use EsLite\Index\TermInfo;
use EsLite\Support\Database\Assignment;
use EsLite\Support\Database\Connection;
use EsLite\Support\Database\RowPlaceholders;

final class TermRepository
{
    public function __construct(private readonly Connection $connection)
    {
    }

    public function findMany(array $terms): array
    {
        if ($terms === []) {
            return [];
        }

        $found = [];

        foreach (array_chunk(array_values(array_unique($terms)), 500) as $chunk) {
            $rows = $this->connection->select(
                sprintf(
                    'SELECT id, term, document_frequency, total_frequency FROM terms WHERE term IN (%s)',
                    RowPlaceholders::list(count($chunk)),
                ),
                $chunk,
            );

            foreach ($rows as $row) {
                $info = TermInfo::fromRow($row);
                $found[$info->term] = $info;
            }
        }

        return $found;
    }

    public function ensure(array $terms): array
    {
        $terms = array_values(array_unique($terms));

        if ($terms === []) {
            return [];
        }

        $existing = $this->findMany($terms);
        $missing = array_values(array_diff($terms, array_keys($existing)));

        if ($missing !== []) {
            $this->connection->upsert(
                'terms',
                ['term', 'document_frequency', 'total_frequency'],
                array_map(
                    static fn (string $term): array => ['term' => $term, 'document_frequency' => 0, 'total_frequency' => 0],
                    $missing,
                ),
                ['term'],
            );

            $existing += $this->findMany($missing);
        }

        $ids = [];

        foreach ($existing as $term => $info) {
            $ids[$term] = $info->id;
        }

        return $ids;
    }

    public function applyFrequencyDeltas(array $deltas): void
    {
        if ($deltas === []) {
            return;
        }

        $rows = [];

        foreach ($deltas as $term => [$documentDelta, $frequencyDelta]) {
            if ($documentDelta === 0 && $frequencyDelta === 0) {
                continue;
            }

            $rows[] = [
                'term' => (string) $term,
                'document_frequency' => $documentDelta,
                'total_frequency' => $frequencyDelta,
            ];
        }

        $this->connection->upsert('terms', ['term', 'document_frequency', 'total_frequency'], $rows, ['term'], [
            'document_frequency' => Assignment::Increment,
            'total_frequency' => Assignment::Increment,
        ]);
    }

    public function prefix(string $prefix, int $limit): array
    {
        $rows = $this->connection->select(
            'SELECT id, term, document_frequency, total_frequency FROM terms '
            . 'WHERE term >= ? AND term < ? AND document_frequency > 0 '
            . 'ORDER BY document_frequency DESC, term ASC LIMIT ' . max(1, $limit),
            [$prefix, $this->upperBound($prefix)],
        );

        return array_map(TermInfo::fromRow(...), $rows);
    }

    public function matching(string $pattern, int $limit): array
    {
        $prefix = $this->literalPrefix($pattern);
        $bindings = [$this->toLikePattern($pattern)];
        $sql = 'SELECT id, term, document_frequency, total_frequency FROM terms WHERE term LIKE ?';

        if ($prefix !== '') {
            $sql .= ' AND term >= ? AND term < ?';
            $bindings[] = $prefix;
            $bindings[] = $this->upperBound($prefix);
        }

        $sql .= ' AND document_frequency > 0 ORDER BY document_frequency DESC, term ASC LIMIT ' . max(1, $limit);

        return array_map(TermInfo::fromRow(...), $this->connection->select($sql, $bindings));
    }

    public function count(): int
    {
        return (int) $this->connection->selectValue('SELECT COUNT(*) FROM terms WHERE document_frequency > 0');
    }

    public function pruneEmpty(): int
    {
        return $this->connection->execute('DELETE FROM terms WHERE document_frequency = 0');
    }

    public function truncate(): void
    {
        $this->connection->execute('DELETE FROM terms');
    }

    private function upperBound(string $prefix): string
    {
        $last = substr($prefix, -1);

        return substr($prefix, 0, -1) . chr(min(255, ord($last) + 1));
    }

    private function literalPrefix(string $pattern): string
    {
        $prefix = '';

        foreach (str_split($pattern) as $character) {
            if ($character === '*' || $character === '?') {
                break;
            }

            $prefix .= $character;
        }

        return $prefix;
    }

    private function toLikePattern(string $pattern): string
    {
        $escaped = str_replace(['%', '_'], ['\%', '\_'], $pattern);

        return str_replace(['*', '?'], ['%', '_'], $escaped);
    }
}
