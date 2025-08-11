<?php

declare(strict_types=1);

namespace EsLite\Http;

final class Response
{
    public function __construct(
        private readonly int $status,
        private readonly array $headers,
        private readonly string $body,
    ) {
    }

    public static function json(mixed $payload, int $status = 200, array $headers = []): self
    {
        $encoded = json_encode(
            $payload,
            JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION,
        );

        return new self(
            $status,
            array_merge(['content-type' => 'application/json; charset=utf-8'], $headers),
            $encoded === false ? '{"error":{"type":"encoding_failed","message":"Cannot encode response."}}' : $encoded,
        );
    }

    public static function error(string $type, string $message, int $status, array $details = []): self
    {
        $error = ['type' => $type, 'message' => $message];

        if ($details !== []) {
            $error['details'] = $details;
        }

        return self::json(['error' => $error], $status);
    }

    public static function noContent(array $headers = []): self
    {
        return new self(204, $headers, '');
    }

    public function status(): int
    {
        return $this->status;
    }

    public function headers(): array
    {
        return $this->headers;
    }

    public function body(): string
    {
        return $this->body;
    }

    public function decoded(): array
    {
        $decoded = json_decode($this->body, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function withHeaders(array $headers): self
    {
        return new self($this->status, array_merge($this->headers, $headers), $this->body);
    }

    public function send(): void
    {
        http_response_code($this->status);

        foreach ($this->headers as $name => $value) {
            header(sprintf('%s: %s', $name, $value));
        }

        echo $this->body;
    }
}
