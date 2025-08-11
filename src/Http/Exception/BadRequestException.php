<?php

declare(strict_types=1);

namespace EsLite\Http\Exception;

final class BadRequestException extends HttpException
{
    public static function malformedJson(string $reason): self
    {
        return new self(sprintf('Request body is not valid JSON: %s', $reason), 400, 'malformed_json');
    }

    public static function missingField(string $field): self
    {
        return new self(sprintf('Field "%s" is required.', $field), 400, 'missing_field', ['field' => $field]);
    }

    public static function invalid(string $message, array $details = []): self
    {
        return new self($message, 400, 'invalid_request', $details);
    }
}
