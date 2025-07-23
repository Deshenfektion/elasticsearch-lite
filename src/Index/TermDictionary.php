<?php

declare(strict_types=1);

namespace EsLite\Index;

use EsLite\Repository\TermRepository;
use EsLite\Support\Cache\Cache;

final class TermDictionary
{
    public function __construct(
        private readonly TermRepository $repository,
        private readonly Cache $cache,
    ) {
    }

    public function lookup(array $terms): array
    {
        $terms = array_values(array_unique($terms));
        $resolved = [];
        $unknown = [];

        foreach ($terms as $term) {
            $cached = $this->cache->get($term);

            if ($cached instanceof TermInfo) {
                $resolved[$term] = $cached;

                continue;
            }

            if ($cached === false) {
                continue;
            }

            $unknown[] = $term;
        }

        if ($unknown === []) {
            return $resolved;
        }

        $found = $this->repository->findMany($unknown);

        foreach ($unknown as $term) {
            if (isset($found[$term])) {
                $this->cache->put($term, $found[$term]);
                $resolved[$term] = $found[$term];

                continue;
            }

            $this->cache->put($term, false);
        }

        return $resolved;
    }

    public function find(string $term): ?TermInfo
    {
        return $this->lookup([$term])[$term] ?? null;
    }

    public function documentFrequency(string $term): int
    {
        $info = $this->find($term);

        return $info === null ? 0 : $info->documentFrequency;
    }

    public function expandPrefix(string $prefix, int $limit): array
    {
        return $this->repository->prefix($prefix, $limit);
    }

    public function expandWildcard(string $pattern, int $limit): array
    {
        return $this->repository->matching($pattern, $limit);
    }

    public function count(): int
    {
        return $this->repository->count();
    }
}
