<?php

declare(strict_types=1);

namespace EsLite\Http;

use EsLite\Http\Exception\BadRequestException;
use EsLite\Http\Exception\PayloadTooLargeException;
use JsonException;

final readonly class Request
{
    public function __construct(
        public string $method,
        public string $path,
        public array $query = [],
        public array $body = [],
        public array $headers = [],
        public string $rawBody = '',
    ) {
    }

    public static function create(string $method, string $path, array $query = [], array $body = []): self
    {
        return new self(strtoupper($method), self::normalisePath($path), $query, $body);
    }

    public static function fromGlobals(int $maxBodyBytes = 2097152): self
    {
        $method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
        $target = (string) ($_SERVER['REQUEST_URI'] ?? '/');
        $path = self::normalisePath(parse_url($target, PHP_URL_PATH) ?: '/');
        $headers = self::headers();
        $raw = '';
        $body = [];

        if (!in_array($method, ['GET', 'HEAD', 'OPTIONS'], true)) {
            $raw = (string) file_get_contents('php://input');

            if (strlen($raw) > $maxBodyBytes) {
                throw PayloadTooLargeException::limit($maxBodyBytes);
            }

            $body = self::decode($raw, $headers['content-type'] ?? '');
        }

        return new self($method, $path, $_GET, $body, $headers, $raw);
    }

    public function input(string $key, mixed $default = null): mixed
    {
        return $this->body[$key] ?? $this->query[$key] ?? $default;
    }

    public function parameters(): array
    {
        return array_merge($this->query, $this->body);
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }

    public function integer(string $key, int $default): int
    {
        $value = $this->input($key);

        if (is_int($value)) {
            return $value;
        }

        if (is_string($value) && preg_match('/^\d+$/', $value) === 1) {
            return (int) $value;
        }

        return $default;
    }

    public function string(string $key, string $default = ''): string
    {
        $value = $this->input($key, $default);

        return is_scalar($value) ? trim((string) $value) : $default;
    }

    private static function decode(string $raw, string $contentType): array
    {
        if (trim($raw) === '') {
            return [];
        }

        if (str_contains($contentType, 'application/x-www-form-urlencoded')) {
            $parsed = [];
            parse_str($raw, $parsed);

            return $parsed;
        }

        try {
            $decoded = json_decode($raw, true, 64, JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw BadRequestException::malformedJson($exception->getMessage());
        }

        return is_array($decoded) ? $decoded : ['value' => $decoded];
    }

    private static function headers(): array
    {
        $headers = [];

        foreach ($_SERVER as $key => $value) {
            if (str_starts_with((string) $key, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr((string) $key, 5)))] = (string) $value;
            }
        }

        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }

        return $headers;
    }

    private static function normalisePath(string $path): string
    {
        $path = '/' . trim($path, '/');
        $path = (string) preg_replace('#/+#', '/', $path);

        if (str_starts_with($path, '/api/')) {
            $path = substr($path, 4);
        } elseif ($path === '/api') {
            $path = '/';
        }

        return $path === '' ? '/' : $path;
    }
}
