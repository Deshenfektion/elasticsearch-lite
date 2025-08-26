<?php

declare(strict_types=1);

namespace EsLite\Search;

use EsLite\Index\DocIdIterator;
use EsLite\Index\IndexReader;
use EsLite\Query\Node\Query;
use EsLite\Ranking\Explanation;
use EsLite\Search\Collector\TopScoreCollector;
use EsLite\Search\Filter\DocumentSet;
use EsLite\Search\Scorer\FilteredScorer;
use EsLite\Search\Scorer\Scorer;

final class Searcher
{
    private const int MAX_MATCHED_IDS = 20000;

    public function __construct(
        private readonly IndexReader $reader,
        private readonly QueryPlanner $planner,
        private readonly ScorerFactory $scorers,
    ) {
    }

    public function plan(Query $query): PlannedQuery
    {
        return $this->planner->plan($query);
    }

    public function search(
        PlannedQuery $planned,
        ?DocumentSet $filter,
        int $from,
        int $size,
        bool $collectMatches = true,
    ): TopDocs {
        $scorer = $this->scorer($planned, $filter);
        $collector = new TopScoreCollector(max($from + $size, 1));
        $matchedIds = [];
        $collected = 0;

        while (($documentId = $scorer->next()) !== DocIdIterator::NO_MORE_DOCS) {
            $collector->collect($documentId, $scorer->score());

            if ($collectMatches && $collected < self::MAX_MATCHED_IDS) {
                $matchedIds[] = $documentId;
                $collected++;
            }
        }

        return new TopDocs(
            $collector->totalHits(),
            array_slice($collector->scoreDocs(), $from, $size),
            $collector->maxScore(),
            $matchedIds,
        );
    }

    public function explain(PlannedQuery $planned, ?DocumentSet $filter, int $documentId): Explanation
    {
        $scorer = $this->scorer($planned, $filter);

        if ($scorer->advance($documentId) !== $documentId) {
            return Explanation::of('document does not match the query', 0.0);
        }

        return $scorer->explain($documentId);
    }

    private function scorer(PlannedQuery $planned, ?DocumentSet $filter): Scorer
    {
        $postingLists = $this->reader->postingLists($planned->terms, $planned->needsPositions);
        $scorer = $this->scorers->create(
            $planned->rewritten,
            $postingLists,
            $this->reader->collectionStatistics(),
            $filter,
        );

        if ($filter === null) {
            return $scorer;
        }

        return new FilteredScorer($scorer, $filter);
    }
}
