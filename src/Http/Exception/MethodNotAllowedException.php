<?php

declare(strict_types=1);

namespace EsLite\Http\Exception;

final class MethodNotAllowedException extends HttpException
{
    public static function for(string $method, string $path, array $allowed): self
    {
        return new self(
            sprintf('%s is not allowed on %s.', $method, $path),
            405,
            'method_not_allowed',
            ['allowed' => $allowed],
        );
    }
}
