<?php

declare(strict_types=1);

namespace EsLite\Support\Database;

use EsLite\Exception\StorageException;
use EsLite\Support\Config;
use PDO;
use PDOException;

final class ConnectionFactory
{
    private const array SUPPORTED = ['mysql', 'sqlite'];

    public static function fromConfig(Config $config): Connection
    {
        $driver = $config->string('app.database.driver');

        return match ($driver) {
            'mysql' => self::mysql($config),
            'sqlite' => self::sqlite($config->string('app.database.database')),
            default => throw StorageException::unsupportedDriver($driver, self::SUPPORTED),
        };
    }

    public static function sqlite(string $database): Connection
    {
        $pdo = self::connect(sprintf('sqlite:%s', $database), null, null);
        $pdo->exec('PRAGMA journal_mode = WAL');
        $pdo->exec('PRAGMA synchronous = NORMAL');
        $pdo->exec('PRAGMA foreign_keys = ON');
        $pdo->exec('PRAGMA temp_store = MEMORY');

        return new Connection($pdo, new SqliteDialect());
    }

    public static function memory(): Connection
    {
        return self::sqlite(':memory:');
    }

    private static function mysql(Config $config): Connection
    {
        $dsn = sprintf(
            'mysql:host=%s;port=%d;dbname=%s;charset=%s',
            $config->string('app.database.host'),
            $config->int('app.database.port'),
            $config->string('app.database.database'),
            $config->string('app.database.charset'),
        );

        $pdo = self::connect(
            $dsn,
            $config->string('app.database.username'),
            $config->string('app.database.password', ''),
        );

        return new Connection($pdo, new MySqlDialect());
    }

    private static function connect(string $dsn, ?string $username, ?string $password): PDO
    {
        try {
            return new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_STRINGIFY_FETCHES => false,
            ]);
        } catch (PDOException $exception) {
            throw StorageException::connectionFailed($dsn, $exception);
        }
    }
}
