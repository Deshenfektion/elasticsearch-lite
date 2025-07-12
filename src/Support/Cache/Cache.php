<?php

declare(strict_types=1);

namespace EsLite\Support\Cache;

interface Cache
{
    public function get(string $key): mixed;

    public function put(string $key, mixed $value): void;

    public function remember(string $key, callable $resolver): mixed;

    public function forget(string $key): void;

    public function flush(): void;

    public function statistics(): CacheStatistics;
}
