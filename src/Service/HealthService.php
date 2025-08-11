<?php

declare(strict_types=1);

namespace EsLite\Service;

use EsLite\Index\IndexReader;
use EsLite\Support\Database\Connection;
use EsLite\Support\Database\Migrator;
use EsLite\Support\Stopwatch;
use Throwable;

final class HealthService
{
    public function __construct(
        private readonly Connection $connection,
        private readonly Migrator $migrator,
        private readonly IndexReader $reader,
    ) {
    }

    public function check(): array
    {
        $stopwatch = new Stopwatch();
        $checks = [
            'database' => $this->database(),
            'migrations' => $this->migrations(),
            'index' => $this->index(),
        ];

        $healthy = array_reduce(
            $checks,
            static fn (bool $carry, array $check): bool => $carry && $check['ok'],
            true,
        );

        return [
            'status' => $healthy ? 'ok' : 'degraded',
            'driver' => $this->connection->dialect()->name(),
            'checks' => $checks,
            'took_ms' => $stopwatch->elapsedMillis(),
        ];
    }

    private function database(): array
    {
        try {
            $this->connection->selectValue('SELECT 1');

            return ['ok' => true];
        } catch (Throwable $exception) {
            return ['ok' => false, 'error' => $exception->getMessage()];
        }
    }

    private function migrations(): array
    {
        try {
            $pending = $this->migrator->pendingVersions();

            return ['ok' => $pending === [], 'pending' => $pending];
        } catch (Throwable $exception) {
            return ['ok' => false, 'error' => $exception->getMessage()];
        }
    }

    private function index(): array
    {
        try {
            $statistics = $this->reader->collectionStatistics();

            return [
                'ok' => true,
                'documents' => $statistics->documentCount,
                'terms' => $statistics->termCount,
            ];
        } catch (Throwable $exception) {
            return ['ok' => false, 'error' => $exception->getMessage()];
        }
    }
}
