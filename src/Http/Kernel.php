<?php

declare(strict_types=1);

namespace EsLite\Http;

use Throwable;

final class Kernel
{
    public function __construct(
        private readonly Router $router,
        private readonly ExceptionMapper $mapper,
        private readonly string $corsOrigin = '*',
    ) {
    }

    public function handle(Request $request): Response
    {
        if ($request->method === 'OPTIONS') {
            return Response::noContent()->withHeaders($this->corsHeaders());
        }

        try {
            $response = $this->router->dispatch($request);
        } catch (Throwable $exception) {
            $response = $this->mapper->map($exception);
        }

        return $response->withHeaders($this->corsHeaders());
    }

    public function routes(): array
    {
        return $this->router->routes();
    }

    private function corsHeaders(): array
    {
        return [
            'access-control-allow-origin' => $this->corsOrigin,
            'access-control-allow-methods' => 'GET, POST, PUT, DELETE, OPTIONS',
            'access-control-allow-headers' => 'content-type',
            'x-content-type-options' => 'nosniff',
        ];
    }
}
