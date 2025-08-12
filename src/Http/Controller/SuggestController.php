<?php

declare(strict_types=1);

namespace EsLite\Http\Controller;

use EsLite\Http\Request;
use EsLite\Http\Response;
use EsLite\Service\SuggestService;

final class SuggestController
{
    public function __construct(
        private readonly SuggestService $suggest,
        private readonly int $defaultSize = 8,
    ) {
    }

    public function suggest(Request $request): Response
    {
        $size = max(1, min($request->integer('size', $this->defaultSize), 25));

        return Response::json($this->suggest->suggest($request->string('q'), $size));
    }
}
