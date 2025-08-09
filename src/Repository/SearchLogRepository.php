<?php

declare(strict_types=1);

namespace EsLite\Repository;

use EsLite\Support\Clock;
use EsLite\Support\Database\Connection;
use EsLite\Support\SystemClock;

final class SearchLogRepository
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Clock $clock = new SystemClock(),
    ) {
    }

    public function log(string $query, array $filters, int $hitCount, int $tookMicros): void
    {
        $this->connection->execute(
            'INSERT INTO search_logs (query, normalised_query, filters, hit_count, took_us, created_at) '
            . 'VALUES (?, ?, ?, ?, ?, ?)',
            [
                mb_substr($query, 0, 512),
                mb_substr(self::normalise($query), 0, 191),
                $filters === [] ? null : json_encode($filters, JSON_UNESCAPED_UNICODE),
                $hitCount,
                $tookMicros,
                $this->clock->now()->format('Y-m-d H:i:s'),
            ],
        );
    }

    public function popular(int $limit = 10, ?string $prefix = null): array
    {
        $sql = 'SELECT normalised_query, COUNT(*) AS total FROM search_logs WHERE hit_count > 0';
        $bindings = [];

        if ($prefix !== null && $prefix !== '') {
            $sql .= ' AND normalised_query LIKE ?';
            $bindings[] = str_replace(['%', '_'], ['\%', '\_'], self::normalise($prefix)) . '%';
        }

        $sql .= ' GROUP BY normalised_query ORDER BY total DESC, normalised_query ASC LIMIT ' . max(1, $limit);

        $suggestions = [];

        foreach ($this->connection->selectPairs($sql, $bindings) as $query => $total) {
            $suggestions[] = ['query' => (string) $query, 'searches' => (int) $total];
        }

        return $suggestions;
    }

    public function statistics(): array
    {
        $row = $this->connection->selectOne(
            'SELECT COUNT(*) AS total, AVG(took_us) AS average_us, MAX(took_us) AS slowest_us, '
            . 'SUM(CASE WHEN hit_count = 0 THEN 1 ELSE 0 END) AS empty_results FROM search_logs',
        ) ?? [];

        return [
            'total' => (int) ($row['total'] ?? 0),
            'average_ms' => round(((float) ($row['average_us'] ?? 0)) / 1000, 3),
            'slowest_ms' => round(((int) ($row['slowest_us'] ?? 0)) / 1000, 3),
            'empty_results' => (int) ($row['empty_results'] ?? 0),
        ];
    }

    public function recent(int $limit = 20): array
    {
        return $this->connection->select(
            'SELECT query, hit_count, took_us, created_at FROM search_logs '
            . 'ORDER BY id DESC LIMIT ' . max(1, $limit),
        );
    }

    public function truncate(): void
    {
        $this->connection->execute('DELETE FROM search_logs');
    }

    public static function normalise(string $query): string
    {
        return trim((string) preg_replace('/\s+/u', ' ', mb_strtolower($query, 'UTF-8')));
    }
}
