<?php

declare(strict_types=1);

namespace EsLite\Http\Controller;

use EsLite\Http\Request;
use EsLite\Http\Response;
use EsLite\Service\ReindexService;

final class IndexController
{
    public function __construct(private readonly ReindexService $reindex)
    {
    }

    public function reindex(Request $request): Response
    {
        return Response::json($this->reindex->reindex(), 202);
    }
}
