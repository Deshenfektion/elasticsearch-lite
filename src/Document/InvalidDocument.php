<?php

declare(strict_types=1);

namespace EsLite\Document;

use EsLite\Exception\EsLiteException;
use InvalidArgumentException;

final class InvalidDocument extends InvalidArgumentException implements EsLiteException
{
    public static function missingField(string $field): self
    {
        return new self(sprintf('Document field "%s" is required.', $field));
    }

    public static function invalidField(string $field, string $expected): self
    {
        return new self(sprintf('Document field "%s" must be %s.', $field, $expected));
    }

    public static function tooLong(string $field, int $limit): self
    {
        return new self(sprintf('Document field "%s" must be at most %d characters.', $field, $limit));
    }
}
