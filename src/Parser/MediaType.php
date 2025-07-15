<?php

declare(strict_types=1);

namespace EsLite\Parser;

final class MediaType
{
    private const array ALIASES = [
        'text' => 'text/plain',
        'txt' => 'text/plain',
        'plain' => 'text/plain',
        'html' => 'text/html',
        'htm' => 'text/html',
        'json' => 'application/json',
        'markdown' => 'text/markdown',
        'md' => 'text/markdown',
    ];

    public static function normalise(string $mediaType): string
    {
        $value = strtolower(trim($mediaType));
        $value = explode(';', $value, 2)[0];
        $value = trim($value);

        return self::ALIASES[$value] ?? ($value === '' ? 'text/plain' : $value);
    }

    public static function fromPath(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        return self::ALIASES[$extension] ?? 'text/plain';
    }
}
