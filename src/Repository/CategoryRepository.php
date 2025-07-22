<?php

declare(strict_types=1);

namespace EsLite\Repository;

use EsLite\Support\Database\Connection;

final class CategoryRepository
{
    private array $identifiers = [];

    public function __construct(private readonly Connection $connection)
    {
    }

    public function ensure(?string $name): ?int
    {
        if ($name === null || trim($name) === '') {
            return null;
        }

        $name = trim($name);

        if (isset($this->identifiers[$name])) {
            return $this->identifiers[$name];
        }

        $id = $this->find($name);

        if ($id === null) {
            $this->connection->upsert('categories', ['name'], [['name' => $name]], ['name']);
            $id = $this->find($name);
        }

        return $this->identifiers[$name] = (int) $id;
    }

    public function find(string $name): ?int
    {
        $id = $this->connection->selectValue('SELECT id FROM categories WHERE name = ?', [$name]);

        return $id === null ? null : (int) $id;
    }

    public function counts(int $limit = 50): array
    {
        return $this->connection->selectPairs(
            'SELECT c.name, COUNT(d.id) AS total FROM categories c '
            . 'JOIN documents d ON d.category_id = c.id GROUP BY c.name '
            . 'ORDER BY total DESC, c.name ASC LIMIT ' . max(1, $limit),
        );
    }

    public function pruneUnused(): int
    {
        return $this->connection->execute(
            'DELETE FROM categories WHERE id NOT IN (SELECT category_id FROM documents WHERE category_id IS NOT NULL)',
        );
    }
}
