<?php

declare(strict_types=1);

namespace EsLite\Support;

final class Env
{
    private static array $overrides = [];

    public static function get(string $key, string|int|float|bool|null $default = null): string|int|float|bool|null
    {
        if (array_key_exists($key, self::$overrides)) {
            return self::$overrides[$key];
        }

        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        if ($value === false || $value === '') {
            return $default;
        }

        return self::coerce((string) $value);
    }

    public static function set(string $key, string|int|float|bool|null $value): void
    {
        self::$overrides[$key] = $value;
    }

    public static function reset(): void
    {
        self::$overrides = [];
    }

    public static function loadFile(string $path): void
    {
        if (!is_readable($path)) {
            return;
        }

        $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) ?: [];

        foreach ($lines as $line) {
            $line = trim($line);

            if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
                continue;
            }

            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\"'");

            if (getenv($key) === false && !isset($_ENV[$key])) {
                $_ENV[$key] = $value;
            }
        }
    }

    private static function coerce(string $value): string|int|float|bool
    {
        return match (strtolower($value)) {
            'true', '(true)' => true,
            'false', '(false)' => false,
            default => self::coerceNumeric($value),
        };
    }

    private static function coerceNumeric(string $value): string|int|float
    {
        if (preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        if (preg_match('/^-?\d*\.\d+$/', $value) === 1) {
            return (float) $value;
        }

        return $value;
    }
}
