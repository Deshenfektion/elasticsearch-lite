<?php

declare(strict_types=1);

namespace EsLite\Service;

use EsLite\Analysis\Analyzer;
use EsLite\Index\TermDictionary;
use EsLite\Index\TermInfo;
use EsLite\Repository\SearchLogRepository;

final class SuggestService
{
    public function __construct(
        private readonly TermDictionary $dictionary,
        private readonly SearchLogRepository $logs,
        private readonly Analyzer $analyzer,
        private readonly int $minimumPrefix = 2,
    ) {
    }

    public function suggest(string $input, int $size = 8): array
    {
        $trimmed = trim($input);

        if (mb_strlen($trimmed) < $this->minimumPrefix) {
            return ['prefix' => $trimmed, 'terms' => [], 'queries' => []];
        }

        $words = preg_split('/\s+/u', $trimmed) ?: [];
        $last = (string) end($words);
        $completed = array_slice($words, 0, -1);
        $prefix = $this->analyzer->normaliseTerm($last);

        $terms = [];

        foreach ($this->dictionary->expandPrefix($prefix, $size) as $info) {
            $terms[] = [
                'term' => $info->term,
                'documents' => $info->documentFrequency,
                'query' => trim(implode(' ', [...$completed, $info->term])),
            ];
        }

        return [
            'prefix' => $trimmed,
            'terms' => $terms,
            'queries' => $this->logs->popular($size, $trimmed),
        ];
    }

    public function completions(string $prefix, int $size): array
    {
        return array_map(
            static fn (TermInfo $info): string => $info->term,
            $this->dictionary->expandPrefix($this->analyzer->normaliseTerm($prefix), $size),
        );
    }
}
