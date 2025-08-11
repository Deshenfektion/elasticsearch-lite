<?php

declare(strict_types=1);

namespace EsLite\Service;

use EsLite\Analysis\Analyzer;
use EsLite\Index\FieldRegistry;
use EsLite\Index\IndexReader;
use EsLite\Ranking\RankingConfiguration;
use EsLite\Repository\CategoryRepository;
use EsLite\Repository\PostingRepository;
use EsLite\Repository\SearchLogRepository;
use EsLite\Repository\TagRepository;

final class StatisticsService
{
    public function __construct(
        private readonly IndexReader $reader,
        private readonly PostingRepository $postings,
        private readonly CategoryRepository $categories,
        private readonly TagRepository $tags,
        private readonly SearchLogRepository $logs,
        private readonly FieldRegistry $fields,
        private readonly RankingConfiguration $ranking,
        private readonly Analyzer $analyzer,
    ) {
    }

    public function statistics(): array
    {
        $collection = $this->reader->collectionStatistics();
        $fields = [];

        foreach ($this->fields->names() as $name) {
            $fieldId = $this->fields->id($name);

            $fields[$name] = [
                'id' => $fieldId,
                'boost' => $this->fields->boost($name),
                'documents' => $collection->fieldDocumentCount($fieldId),
                'average_length' => round($collection->averageFieldLength($fieldId), 2),
            ];
        }

        return [
            'index' => [
                'documents' => $collection->documentCount,
                'terms' => $collection->termCount,
                'postings' => $this->postings->count(),
                'tokens' => $collection->tokenCount,
                'tokens_per_posting' => $this->tokensPerPosting($collection->postingCount),
            ],
            'fields' => $fields,
            'analysis' => $this->analyzer->describe(),
            'ranking' => $this->ranking->toArray(),
            'facets' => [
                'categories' => $this->categories->counts(),
                'tags' => $this->tags->counts(20),
            ],
            'searches' => $this->logs->statistics(),
            'caches' => $this->reader->cacheStatistics(),
        ];
    }

    public function popularQueries(int $limit = 10): array
    {
        return $this->logs->popular($limit);
    }

    private function tokensPerPosting(int $postingCount): ?float
    {
        if ($postingCount <= 0) {
            return null;
        }

        return round(($this->reader->collectionStatistics()->tokenCount * 1.0) / $postingCount, 2);
    }
}
