<?php

declare(strict_types=1);

namespace EsLite\Http;

use EsLite\Http\Exception\MethodNotAllowedException;
use EsLite\Http\Exception\NotFoundException;

final class Router
{
    private array $routes = [];

    public function get(string $path, callable $handler): void
    {
        $this->add('GET', $path, $handler);
    }

    public function post(string $path, callable $handler): void
    {
        $this->add('POST', $path, $handler);
    }

    public function put(string $path, callable $handler): void
    {
        $this->add('PUT', $path, $handler);
    }

    public function delete(string $path, callable $handler): void
    {
        $this->add('DELETE', $path, $handler);
    }

    public function dispatch(Request $request): Response
    {
        $allowed = [];

        foreach ($this->routes as $route) {
            $parameters = $route->match($request->path);

            if ($parameters === null) {
                continue;
            }

            if ($route->method !== $request->method) {
                $allowed[] = $route->method;

                continue;
            }

            return ($route->handler)($request, $parameters);
        }

        if ($allowed !== []) {
            throw MethodNotAllowedException::for($request->method, $request->path, array_unique($allowed));
        }

        throw NotFoundException::route($request->method, $request->path);
    }

    public function routes(): array
    {
        return array_map(
            static fn (Route $route): string => $route->method . ' ' . $route->path,
            $this->routes,
        );
    }

    private function add(string $method, string $path, callable $handler): void
    {
        $this->routes[] = new Route($method, $path, $handler);
    }
}
