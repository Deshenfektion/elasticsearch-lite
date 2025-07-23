<?php

declare(strict_types=1);

namespace EsLite\Index;

use EsLite\Index\Statistics\CollectionStatistics;
use EsLite\Repository\IndexStateRepository;
use EsLite\Repository\PostingRepository;

final class IndexReader
{
    private ?CollectionStatistics $statistics = null;

    public function __construct(
        private readonly TermDictionary $dictionary,
        private readonly PostingRepository $postings,
        private readonly IndexStateRepository $state,
        private readonly FieldRegistry $fields,
        private readonly IndexCache $cache,
    ) {
    }

    public function dictionary(): TermDictionary
    {
        return $this->dictionary;
    }

    public function fields(): FieldRegistry
    {
        return $this->fields;
    }

    public function postingLists(array $terms, bool $withPositions = true): array
    {
        $terms = array_values(array_unique($terms));

        if ($terms === []) {
            return [];
        }

        $lists = [];
        $pending = [];

        foreach ($terms as $term) {
            $cached = $this->cache->postings()->get($term . ':' . ($withPositions ? '1' : '0'));

            if ($cached instanceof PostingList) {
                $lists[$term] = $cached;

                continue;
            }

            $pending[] = $term;
        }

        if ($pending === []) {
            return $lists;
        }

        $infos = $this->dictionary->lookup($pending);
        $termIds = [];

        foreach ($infos as $term => $info) {
            $termIds[$info->id] = $term;
        }

        $grouped = [];

        foreach ($this->postings->fetch(array_keys($termIds), $withPositions) as $row) {
            $grouped[(int) $row['term_id']][] = Posting::fromRow($row);
        }

        foreach ($infos as $term => $info) {
            $list = new PostingList($term, $info->id, $info->documentFrequency, $grouped[$info->id] ?? []);
            $lists[$term] = $list;
            $this->cache->postings()->put($term . ':' . ($withPositions ? '1' : '0'), $list);
        }

        foreach ($pending as $term) {
            $lists[$term] ??= PostingList::empty($term);
        }

        return $lists;
    }

    public function postingList(string $term, bool $withPositions = true): PostingList
    {
        return $this->postingLists([$term], $withPositions)[$term] ?? PostingList::empty($term);
    }

    public function collectionStatistics(): CollectionStatistics
    {
        if ($this->statistics !== null) {
            return $this->statistics;
        }

        $state = $this->state->all();
        $documentCounts = [];
        $totalLengths = [];

        foreach ($this->fields->ids() as $fieldId) {
            $documentCounts[$fieldId] = $state[IndexStateRepository::fieldDocumentKey($fieldId)] ?? 0;
            $totalLengths[$fieldId] = $state[IndexStateRepository::fieldLengthKey($fieldId)] ?? 0;
        }

        return $this->statistics = new CollectionStatistics(
            $state[IndexStateRepository::DOCUMENT_COUNT] ?? 0,
            $documentCounts,
            $totalLengths,
            $this->dictionary->count(),
            $state['posting_count'] ?? 0,
            $state['token_count'] ?? 0,
        );
    }

    public function refresh(): void
    {
        $this->statistics = null;
        $this->cache->flush();
    }

    public function cacheStatistics(): array
    {
        return $this->cache->statistics();
    }
}
