<?php

declare(strict_types=1);

namespace EsLite\Repository;

use EsLite\Support\Database\Connection;
use EsLite\Support\Database\RowPlaceholders;

final class TagRepository
{
    private array $identifiers = [];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function ensureMany(array $names): array
    {
        $names = array_values(array_unique(array_filter(array_map(trim(...), $names))));

        if ($names === []) {
            return [];
        }

        $missing = array_values(array_diff($names, array_keys($this->identifiers)));

        if ($missing !== []) {
            $this->connection->upsert(
                'tags',
                ['name'],
                array_map(static fn (string $name): array => ['name' => $name], $missing),
                ['name'],
            );

            $rows = $this->connection->selectPairs(
                sprintf('SELECT name, id FROM tags WHERE name IN (%s)', RowPlaceholders::list(count($missing))),
                $missing,
            );

            foreach ($rows as $name => $id) {
                $this->identifiers[(string) $name] = (int) $id;
            }
        }

        $ids = [];

        foreach ($names as $name) {
            if (isset($this->identifiers[$name])) {
                $ids[$name] = $this->identifiers[$name];
            }
        }

        return $ids;
    }

    public function sync(int $documentId, array $names): void
    {
        $ids = $this->ensureMany($names);
        $this->connection->execute('DELETE FROM document_tags WHERE document_id = ?', [$documentId]);

        if ($ids === []) {
            return;
        }

        $this->connection->upsert(
            'document_tags',
            ['document_id', 'tag_id'],
            array_map(
                static fn (int $tagId): array => ['document_id' => $documentId, 'tag_id' => $tagId],
                array_values($ids),
            ),
            ['document_id', 'tag_id'],
        );
    }

    public function idsFor(array $names): array
    {
        if ($names === []) {
            return [];
        }

        return array_map(intval(...), $this->connection->selectColumn(
            sprintf('SELECT id FROM tags WHERE name IN (%s)', RowPlaceholders::list(count($names))),
            array_values($names),
        ));
    }

    public function counts(int $limit = 50): array
    {
        return $this->connection->selectPairs(
            'SELECT t.name, COUNT(dt.document_id) AS total FROM tags t '
            . 'JOIN document_tags dt ON dt.tag_id = t.id GROUP BY t.name '
            . 'ORDER BY total DESC, t.name ASC LIMIT ' . max(1, $limit),
        );
    }

    public function pruneUnused(): int
    {
        return $this->connection->execute(
            'DELETE FROM tags WHERE id NOT IN (SELECT tag_id FROM document_tags)',
        );
    }
}
