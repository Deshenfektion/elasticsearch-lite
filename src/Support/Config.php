<?php

declare(strict_types=1);

namespace EsLite\Support;

use EsLite\Exception\ConfigurationException;

final class Config
{
    public function __construct(private array $values = [])
    {
    }

    public static function load(string $directory): self
    {
        $values = [];

        foreach (glob(rtrim($directory, '/') . '/*.php') ?: [] as $file) {
            $values[basename($file, '.php')] = require $file;
        }

        return new self($values);
    }

    public function has(string $path): bool
    {
        return $this->find($path) !== null;
    }

    public function get(string $path, mixed $default = null): mixed
    {
        $found = $this->find($path);

        return $found === null ? $default : $found[0];
    }

    public function string(string $path, ?string $default = null): string
    {
        $value = $this->require($path, $default);

        if (is_string($value)) {
            return $value;
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        throw ConfigurationException::unexpectedType($path, 'a string', get_debug_type($value));
    }

    public function int(string $path, ?int $default = null): int
    {
        $value = $this->require($path, $default);

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^-?\d+$/', $value) === 1) {
            return (int) $value;
        }

        throw ConfigurationException::unexpectedType($path, 'an integer', get_debug_type($value));
    }

    public function float(string $path, ?float $default = null): float
    {
        $value = $this->require($path, $default);

        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        throw ConfigurationException::unexpectedType($path, 'a number', get_debug_type($value));
    }

    public function bool(string $path, ?bool $default = null): bool
    {
        $value = $this->require($path, $default);

        if (is_bool($value)) {
            return $value;
        }

        if (is_int($value) && ($value === 0 || $value === 1)) {
            return $value === 1;
        }

        if (is_string($value)) {
            return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
        }

        throw ConfigurationException::unexpectedType($path, 'a boolean', get_debug_type($value));
    }

    public function array(string $path, ?array $default = null): array
    {
        $value = $this->require($path, $default);

        if (is_array($value)) {
            return $value;
        }

        throw ConfigurationException::unexpectedType($path, 'an array', get_debug_type($value));
    }

    public function with(string $path, mixed $value): self
    {
        $values = $this->values;
        $segments = explode('.', $path);
        $cursor = &$values;

        foreach ($segments as $segment) {
            if (!isset($cursor[$segment]) || !is_array($cursor[$segment])) {
                $cursor[$segment] = [];
            }

            $cursor = &$cursor[$segment];
        }

        $cursor = $value;

        return new self($values);
    }

    public function merge(array $overrides): self
    {
        return new self($this->mergeRecursive($this->values, $overrides));
    }

    public function all(): array
    {
        return $this->values;
    }

    private function require(string $path, mixed $default): mixed
    {
        $found = $this->find($path);

        if ($found !== null) {
            return $found[0];
        }

        if ($default !== null) {
            return $default;
        }

        throw ConfigurationException::missing($path);
    }

    private function find(string $path): ?array
    {
        $cursor = $this->values;

        foreach (explode('.', $path) as $segment) {
            if (!is_array($cursor) || !array_key_exists($segment, $cursor)) {
                return null;
            }

            $cursor = $cursor[$segment];
        }

        return [$cursor];
    }

    private function mergeRecursive(array $base, array $overrides): array
    {
        foreach ($overrides as $key => $value) {
            if (is_array($value) && isset($base[$key]) && is_array($base[$key]) && !array_is_list($value)) {
                $base[$key] = $this->mergeRecursive($base[$key], $value);

                continue;
            }

            $base[$key] = $value;
        }

        return $base;
    }
}
