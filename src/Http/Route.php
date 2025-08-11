<?php

declare(strict_types=1);

namespace EsLite\Http;

use Closure;

final readonly class Route
{
    public Closure $handler;

    private string $pattern;

    public function __construct(
        public string $method,
        public string $path,
        callable $handler,
    ) {
        $this->handler = $handler instanceof Closure ? $handler : Closure::fromCallable($handler);
        $this->pattern = $this->compile($path);
    }

    public function match(string $path): ?array
    {
        $matches = [];

        if (preg_match($this->pattern, $path, $matches) !== 1) {
            return null;
        }

        $parameters = [];

        foreach ($matches as $key => $value) {
            if (is_string($key)) {
                $parameters[$key] = rawurldecode($value);
            }
        }

        return $parameters;
    }

    private function compile(string $path): string
    {
        $escaped = preg_quote($path, '#');
        $pattern = (string) preg_replace('#\\\\\{([a-z_]+)\\\\\}#', '(?P<$1>[^/]+)', $escaped);

        return '#^' . $pattern . '$#';
    }
}
