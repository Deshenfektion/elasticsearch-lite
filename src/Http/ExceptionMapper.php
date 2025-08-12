<?php

declare(strict_types=1);

namespace EsLite\Http;

use EsLite\Document\InvalidDocument;
use EsLite\Exception\ConfigurationException;
use EsLite\Exception\StorageException;
use EsLite\Http\Exception\HttpException;
use EsLite\Parser\Exception\ParseException;
use EsLite\Parser\Exception\UnsupportedMediaTypeException;
use EsLite\Query\Exception\QueryParseException;
use Throwable;

final class ExceptionMapper
{
    public function __construct(private readonly bool $debug = false)
    {
    }

    public function map(Throwable $exception): Response
    {
        return match (true) {
            $exception instanceof HttpException => Response::error(
                $exception->type(),
                $exception->getMessage(),
                $exception->status(),
                $exception->details(),
            ),
            $exception instanceof QueryParseException => Response::error(
                'query_parse_error',
                $exception->getMessage(),
                400,
                ['position' => $exception->position],
            ),
            $exception instanceof InvalidDocument => Response::error(
                'invalid_document',
                $exception->getMessage(),
                422,
            ),
            $exception instanceof UnsupportedMediaTypeException => Response::error(
                'unsupported_media_type',
                $exception->getMessage(),
                415,
            ),
            $exception instanceof ParseException => Response::error(
                'document_parse_error',
                $exception->getMessage(),
                422,
            ),
            $exception instanceof ConfigurationException => Response::error(
                'configuration_error',
                $exception->getMessage(),
                500,
            ),
            $exception instanceof StorageException => Response::error(
                'storage_unavailable',
                $this->debug ? $exception->getMessage() : 'The search index is unavailable.',
                503,
            ),
            default => Response::error(
                'server_error',
                $this->debug ? $exception->getMessage() : 'Unexpected server error.',
                500,
                $this->debug ? ['exception' => $exception::class, 'trace' => $exception->getTraceAsString()] : [],
            ),
        };
    }
}
