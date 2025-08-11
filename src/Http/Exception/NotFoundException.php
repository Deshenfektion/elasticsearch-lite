<?php

declare(strict_types=1);

namespace EsLite\Http\Exception;

final class NotFoundException extends HttpException
{
    public static function route(string $method, string $path): self
    {
        return new self(sprintf('No route matches %s %s.', $method, $path), 404, 'route_not_found');
    }

    public static function document(string $externalId): self
    {
        return new self(
            sprintf('Document "%s" is not indexed.', $externalId),
            404,
            'document_not_found',
            ['id' => $externalId],
        );
    }
}
