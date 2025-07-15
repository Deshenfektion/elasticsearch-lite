<?php

declare(strict_types=1);

namespace EsLite\Parser\Exception;

use EsLite\Exception\EsLiteException;
use RuntimeException;

final class ParseException extends RuntimeException implements EsLiteException
{
    public static function malformed(string $mediaType, string $reason): self
    {
        return new self(sprintf('Cannot parse %s document: %s', $mediaType, $reason));
    }

    public static function emptyContent(string $mediaType): self
    {
        return new self(sprintf('Parsed %s document has no indexable content.', $mediaType));
    }
}
