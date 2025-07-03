<?php

declare(strict_types=1);

namespace EsLite\Exception;

use RuntimeException;

final class ConfigurationException extends RuntimeException implements EsLiteException
{
    public static function missing(string $path): self
    {
        return new self(sprintf('Configuration value "%s" is not set.', $path));
    }

    public static function unexpectedType(string $path, string $expected, string $actual): self
    {
        return new self(sprintf('Configuration value "%s" must be %s, got %s.', $path, $expected, $actual));
    }

    public static function unknown(string $subject, string $name, array $known): self
    {
        return new self(sprintf('Unknown %s "%s". Known: %s.', $subject, $name, implode(', ', $known)));
    }
}
