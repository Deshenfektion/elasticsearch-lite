<?php

declare(strict_types=1);

namespace EsLite\Parser;

final class Text
{
    public static function normaliseWhitespace(string $text): string
    {
        $text = str_replace(["\r\n", "\r", "\x00"], ["\n", "\n", ''], $text);
        $text = (string) preg_replace('/[ \t\x{00a0}]+/u', ' ', $text);
        $text = (string) preg_replace('/ *\n */u', "\n", $text);
        $text = (string) preg_replace('/\n{3,}/u', "\n\n", $text);

        return trim($text);
    }

    public static function firstLine(string $text): string
    {
        $lines = preg_split('/\n/u', $text) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line !== '') {
                return $line;
            }
        }

        return '';
    }

    public static function withoutFirstLine(string $text): string
    {
        $position = mb_strpos($text, "\n");

        return $position === false ? '' : trim(mb_substr($text, $position + 1));
    }

    public static function truncate(string $text, int $length): string
    {
        if (mb_strlen($text) <= $length) {
            return $text;
        }

        $cut = mb_substr($text, 0, $length);
        $lastSpace = mb_strrpos($cut, ' ');

        return rtrim($lastSpace !== false && $lastSpace > $length / 2 ? mb_substr($cut, 0, $lastSpace) : $cut);
    }
}
