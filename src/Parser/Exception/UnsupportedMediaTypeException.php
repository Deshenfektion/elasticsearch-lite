<?php

declare(strict_types=1);

namespace EsLite\Parser\Exception;

use EsLite\Exception\EsLiteException;
use RuntimeException;

final class UnsupportedMediaTypeException extends RuntimeException implements EsLiteException
{
    public static function for(string $mediaType, array $supported): self
    {
        return new self(sprintf(
            'No parser registered for media type "%s". Supported: %s.',
            $mediaType,
            implode(', ', $supported),
        ));
    }
}
