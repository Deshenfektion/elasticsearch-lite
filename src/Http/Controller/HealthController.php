<?php

declare(strict_types=1);

namespace EsLite\Http\Controller;

use EsLite\Http\Request;
use EsLite\Http\Response;
use EsLite\Service\HealthService;

final class HealthController
{
    public function __construct(private readonly HealthService $health)
    {
    }

    public function show(Request $request): Response
    {
        $health = $this->health->check();

        return Response::json($health, $health['status'] === 'ok' ? 200 : 503);
    }
}
