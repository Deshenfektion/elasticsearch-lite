<?php

declare(strict_types=1);

namespace EsLite\Http\Controller;

use EsLite\Http\Request;
use EsLite\Http\Response;
use EsLite\Service\StatisticsService;

final class StatisticsController
{
    public function __construct(private readonly StatisticsService $statistics)
    {
    }

    public function show(Request $request): Response
    {
        return Response::json($this->statistics->statistics());
    }
}
