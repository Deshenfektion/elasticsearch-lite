<?php

declare(strict_types=1);

namespace EsLite\Service;

use EsLite\Highlight\Highlighter;
use EsLite\Highlight\HighlightOptions;
use EsLite\Repository\DocumentRepository;
use EsLite\Search\Filter\DocumentSet;
use EsLite\Search\PlannedQuery;
use EsLite\Search\SearchHit;
use EsLite\Search\SearchRequest;
use EsLite\Search\Searcher;
use EsLite\Search\TopDocs;

final class HitAssembler
{
    public function __construct(
        private readonly DocumentRepository $documents,
        private readonly Highlighter $highlighter,
        private readonly Searcher $searcher,
        private readonly HighlightOptions $options,
    ) {
    }

    public function assemble(
        TopDocs $topDocs,
        PlannedQuery $planned,
        SearchRequest $request,
        ?DocumentSet $filter,
    ): array {
        $documentIds = $topDocs->documentIds();

        if ($documentIds === []) {
            return [];
        }

        $stored = $this->documents->findMany($documentIds);
        $scores = $topDocs->scores();
        $hits = [];

        foreach ($documentIds as $documentId) {
            $document = $stored[$documentId] ?? null;

            if ($document === null) {
                continue;
            }

            $hits[] = new SearchHit(
                $document,
                $scores[$documentId] ?? 0.0,
                $request->highlight ? $this->highlighter->highlight($document, $planned, $this->options) : [],
                $request->explain ? $this->searcher->explain($planned, $filter, $documentId) : null,
            );
        }

        return $hits;
    }
}
