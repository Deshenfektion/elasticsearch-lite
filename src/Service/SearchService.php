<?php

declare(strict_types=1);

namespace EsLite\Service;

use EsLite\Query\Node\MatchAllQuery;
use EsLite\Query\Node\Query;
use EsLite\Query\QueryParser;
use EsLite\Repository\DocumentRepository;
use EsLite\Repository\FacetRepository;
use EsLite\Repository\SearchLogRepository;
use EsLite\Search\Filter\DocumentSet;
use EsLite\Search\Filter\FilterCompiler;
use EsLite\Search\PlannedQuery;
use EsLite\Search\ScoreDoc;
use EsLite\Search\SearchRequest;
use EsLite\Search\SearchResponse;
use EsLite\Search\Searcher;
use EsLite\Search\SortOrder;
use EsLite\Search\TopDocs;
use EsLite\Support\Cache\Cache;
use EsLite\Support\Stopwatch;

final class SearchService
{
    private const int UNIVERSE_LIMIT = 20000;

    public function __construct(
        private readonly QueryParser $parser,
        private readonly Searcher $searcher,
        private readonly FilterCompiler $filters,
        private readonly DocumentRepository $documents,
        private readonly FacetRepository $facets,
        private readonly HitAssembler $assembler,
        private readonly SearchLogRepository $logs,
        private readonly Cache $resultCache,
        private readonly bool $logQueries = true,
    ) {
    }

    public function search(SearchRequest $request): SearchResponse
    {
        $cacheKey = $request->signature();
        $cached = $this->resultCache->get($cacheKey);

        if ($cached instanceof SearchResponse) {
            return $cached->withCacheFlag(true);
        }

        $stopwatch = new Stopwatch();
        $query = $this->parse($request->query);
        $planned = $this->searcher->plan($query);
        $filter = $this->filters->compile($request->filters);
        $universe = $this->universe($planned, $filter);

        $topDocs = $this->execute($planned, $universe ?? $filter, $request);
        $hits = $this->assembler->assemble($topDocs, $planned, $request, $universe ?? $filter);
        $facets = $request->facets ? $this->facets->forDocuments($topDocs->matchedIds) : [];

        $response = new SearchResponse(
            $topDocs->totalHits,
            $hits,
            $stopwatch->elapsedMicros(),
            $request->page(),
            $request->size,
            $topDocs->maxScore,
            $facets,
            $planned->toArray() + ['filters' => $request->filters->toArray(), 'sort' => $request->sort->value],
        );

        $this->resultCache->put($cacheKey, $response);

        if ($this->logQueries && $request->query !== '') {
            $this->logs->log(
                $request->query,
                $request->filters->toArray(),
                $topDocs->totalHits,
                $response->tookMicros,
            );
        }

        return $response;
    }

    private function parse(string $query): Query
    {
        return trim($query) === '' ? new MatchAllQuery() : $this->parser->parse($query);
    }

    private function execute(PlannedQuery $planned, ?DocumentSet $filter, SearchRequest $request): TopDocs
    {
        if ($request->sort === SortOrder::Relevance) {
            return $this->searcher->search($planned, $filter, $request->from, $request->size, $request->facets);
        }

        $unsorted = $this->searcher->search($planned, $filter, 0, 0);
        $ordered = $this->documents->orderByPublished(
            $unsorted->matchedIds,
            $request->from,
            $request->size,
            $request->sort->direction(),
        );

        return new TopDocs(
            $unsorted->totalHits,
            array_map(static fn (int $id): ScoreDoc => new ScoreDoc($id, 0.0), $ordered),
            0.0,
            $unsorted->matchedIds,
        );
    }

    private function universe(PlannedQuery $planned, ?DocumentSet $filter): ?DocumentSet
    {
        if ($filter !== null) {
            return $filter;
        }

        if ($planned->rewritten->kind() !== 'match_all') {
            return null;
        }

        return new DocumentSet($this->documents->idsMatching('', [], self::UNIVERSE_LIMIT));
    }
}
