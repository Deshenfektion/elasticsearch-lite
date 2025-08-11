<?php

declare(strict_types=1);

namespace EsLite\Http\Exception;

use EsLite\Exception\EsLiteException;
use RuntimeException;

class HttpException extends RuntimeException implements EsLiteException
{
    public function __construct(
        string $message,
        private readonly int $status = 500,
        private readonly string $type = 'server_error',
        private readonly array $details = [],
    ) {
        parent::__construct($message);
    }

    public function status(): int
    {
        return $this->status;
    }

    public function type(): string
    {
        return $this->type;
    }

    public function details(): array
    {
        return $this->details;
    }
}
