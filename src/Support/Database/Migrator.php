<?php

declare(strict_types=1);

namespace EsLite\Support\Database;

use EsLite\Exception\ConfigurationException;

final class Migrator
{
    public function __construct(
        private readonly Connection $connection,
        private readonly string $directory,
    ) {
    }

    public function migrate(): array
    {
        $this->ensureRegistry();

        $applied = $this->applied();
        $ran = [];

        foreach ($this->pending($applied) as $version => $statements) {
            $this->apply($version, $statements);
            $ran[] = $version;
        }

        return $ran;
    }

    public function applied(): array
    {
        $this->ensureRegistry();

        return $this->connection->selectColumn('SELECT version FROM schema_migrations ORDER BY version');
    }

    public function pendingVersions(): array
    {
        return array_keys($this->pending($this->applied()));
    }

    private function pending(array $applied): array
    {
        $driver = $this->connection->dialect()->name();
        $pending = [];

        foreach ($this->files() as $version => $file) {
            if (in_array($version, $applied, true)) {
                continue;
            }

            $definition = require $file;

            if (!is_array($definition) || !isset($definition[$driver])) {
                throw new ConfigurationException(sprintf(
                    'Migration "%s" has no statements for driver "%s".',
                    $version,
                    $driver,
                ));
            }

            $pending[$version] = $definition[$driver];
        }

        return $pending;
    }

    private function files(): array
    {
        $files = glob(rtrim($this->directory, '/') . '/*.php') ?: [];
        sort($files);

        $indexed = [];

        foreach ($files as $file) {
            $indexed[basename($file, '.php')] = $file;
        }

        return $indexed;
    }

    private function apply(string $version, array $statements): void
    {
        $run = function () use ($statements): void {
            foreach ($statements as $statement) {
                $this->connection->raw($statement);
            }
        };

        if ($this->connection->dialect()->supportsTransactionalSchema()) {
            $this->connection->transaction(static fn () => $run());
        } else {
            $run();
        }

        $this->connection->execute(
            'INSERT INTO schema_migrations (version, applied_at) VALUES (?, ?)',
            [$version, gmdate('Y-m-d H:i:s')],
        );
    }

    private function ensureRegistry(): void
    {
        $this->connection->raw(
            'CREATE TABLE IF NOT EXISTS schema_migrations ('
            . 'version VARCHAR(64) NOT NULL, applied_at DATETIME NOT NULL, PRIMARY KEY (version))',
        );
    }
}
