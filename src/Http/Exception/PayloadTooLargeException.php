<?php

declare(strict_types=1);

namespace EsLite\Http\Exception;

final class PayloadTooLargeException extends HttpException
{
    public static function limit(int $bytes): self
    {
        return new self(
            sprintf('Request body exceeds the %d byte limit.', $bytes),
            413,
            'payload_too_large',
            ['limit_bytes' => $bytes],
        );
    }
}
