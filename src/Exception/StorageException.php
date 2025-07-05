<?php

declare(strict_types=1);

namespace EsLite\Exception;

use RuntimeException;
use Throwable;

final class StorageException extends RuntimeException implements EsLiteException
{
    public static function queryFailed(string $sql, Throwable $previous): self
    {
        return new self(sprintf('Query failed: %s (%s)', self::summarise($sql), $previous->getMessage()), 0, $previous);
    }

    public static function connectionFailed(string $dsn, Throwable $previous): self
    {
        return new self(sprintf('Cannot connect to "%s": %s', $dsn, $previous->getMessage()), 0, $previous);
    }

    public static function unsupportedDriver(string $driver, array $supported): self
    {
        return new self(sprintf('Unsupported database driver "%s". Supported: %s.', $driver, implode(', ', $supported)));
    }

    private static function summarise(string $sql): string
    {
        $collapsed = (string) preg_replace('/\s+/', ' ', trim($sql));

        return mb_strlen($collapsed) > 160 ? mb_substr($collapsed, 0, 157) . '...' : $collapsed;
    }
}
