<?php

declare(strict_types=1);

namespace EsLite\Repository;

use EsLite\Support\Database\Connection;
use EsLite\Support\Database\RowPlaceholders;

final class FacetRepository
{
    private const int MAX_IDS = 5000;

    public function __construct(private readonly Connection $connection)
    {
    }

    public function forDocuments(array $documentIds, int $limit = 12): array
    {
        if ($documentIds === []) {
            return ['categories' => [], 'tags' => [], 'authors' => [], 'truncated' => false];
        }

        $truncated = count($documentIds) > self::MAX_IDS;
        $ids = array_slice(array_values($documentIds), 0, self::MAX_IDS);
        $placeholders = RowPlaceholders::list(count($ids));

        return [
            'categories' => $this->connection->selectPairs(
                sprintf(
                    'SELECT c.name, COUNT(*) AS total FROM documents d JOIN categories c ON c.id = d.category_id '
                    . 'WHERE d.id IN (%s) GROUP BY c.name ORDER BY total DESC, c.name ASC LIMIT %d',
                    $placeholders,
                    max(1, $limit),
                ),
                $ids,
            ),
            'tags' => $this->connection->selectPairs(
                sprintf(
                    'SELECT t.name, COUNT(*) AS total FROM document_tags dt JOIN tags t ON t.id = dt.tag_id '
                    . 'WHERE dt.document_id IN (%s) GROUP BY t.name ORDER BY total DESC, t.name ASC LIMIT %d',
                    $placeholders,
                    max(1, $limit),
                ),
                $ids,
            ),
            'authors' => $this->connection->selectPairs(
                sprintf(
                    'SELECT d.author, COUNT(*) AS total FROM documents d WHERE d.id IN (%s) AND d.author IS NOT NULL '
                    . 'GROUP BY d.author ORDER BY total DESC, d.author ASC LIMIT %d',
                    $placeholders,
                    max(1, $limit),
                ),
                $ids,
            ),
            'truncated' => $truncated,
        ];
    }
}
