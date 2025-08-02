<?php

declare(strict_types=1);

namespace EsLite\Search;

use EsLite\Index\FieldRegistry;
use EsLite\Index\PostingList;
use EsLite\Index\Statistics\CollectionStatistics;
use EsLite\Query\Node\BooleanClause;
use EsLite\Query\Node\BooleanQuery;
use EsLite\Query\Node\ExpandedQuery;
use EsLite\Query\Node\MatchAllQuery;
use EsLite\Query\Node\PhraseQuery;
use EsLite\Query\Node\Query;
use EsLite\Query\Node\TermQuery;
use EsLite\Query\Occur;
use EsLite\Ranking\RankingConfiguration;
use EsLite\Search\Filter\DocumentSet;
use EsLite\Search\Scorer\ConjunctionScorer;
use EsLite\Search\Scorer\ConstantScoreScorer;
use EsLite\Search\Scorer\DisjunctionScorer;
use EsLite\Search\Scorer\EmptyScorer;
use EsLite\Search\Scorer\ExclusionScorer;
use EsLite\Search\Scorer\PhraseScorer;
use EsLite\Search\Scorer\RequiredOptionalScorer;
use EsLite\Search\Scorer\Scorer;
use EsLite\Search\Scorer\TermScorer;

final class ScorerFactory
{
    public function __construct(
        private readonly RankingConfiguration $ranking,
        private readonly FieldRegistry $fields,
    ) {
    }

    public function create(
        Query $query,
        array $postingLists,
        CollectionStatistics $statistics,
        ?DocumentSet $universe = null,
    ): Scorer {
        return match (true) {
            $query instanceof TermQuery => $this->term($query, $postingLists, $statistics),
            $query instanceof PhraseQuery => $this->phrase($query, $postingLists, $statistics),
            $query instanceof ExpandedQuery => $this->expanded($query, $postingLists, $statistics),
            $query instanceof BooleanQuery => $this->boolean($query, $postingLists, $statistics, $universe),
            $query instanceof MatchAllQuery => $this->matchAll($universe),
            default => new EmptyScorer(),
        };
    }

    private function term(TermQuery $query, array $postingLists, CollectionStatistics $statistics): Scorer
    {
        $list = $postingLists[$query->term] ?? null;

        if (!$list instanceof PostingList || $list->isEmpty()) {
            return new EmptyScorer();
        }

        return new TermScorer(
            $list,
            $this->ranking->model,
            $this->fields,
            $statistics,
            $query->field === null ? null : $this->fields->id($query->field),
            $query->boost,
        );
    }

    private function phrase(PhraseQuery $query, array $postingLists, CollectionStatistics $statistics): Scorer
    {
        $lists = [];

        foreach ($query->terms as $term) {
            $list = $postingLists[$term] ?? null;

            if (!$list instanceof PostingList || $list->isEmpty()) {
                return new EmptyScorer();
            }

            $lists[] = $list;
        }

        return new PhraseScorer(
            $lists,
            $this->ranking->model,
            $this->fields,
            $statistics,
            $query->field === null ? null : $this->fields->id($query->field),
            $query->boost * $this->ranking->phraseBoost,
            $query->offsets,
        );
    }

    private function expanded(ExpandedQuery $query, array $postingLists, CollectionStatistics $statistics): Scorer
    {
        $scorers = [];

        foreach ($query->terms as $term) {
            $list = $postingLists[$term] ?? null;

            if (!$list instanceof PostingList || $list->isEmpty()) {
                continue;
            }

            $scorers[] = new TermScorer(
                $list,
                $this->ranking->model,
                $this->fields,
                $statistics,
                $query->field === null ? null : $this->fields->id($query->field),
            );
        }

        return match (count($scorers)) {
            0 => new EmptyScorer(),
            1 => $scorers[0],
            default => new DisjunctionScorer($scorers, 1, false),
        };
    }

    private function boolean(
        BooleanQuery $query,
        array $postingLists,
        CollectionStatistics $statistics,
        ?DocumentSet $universe,
    ): Scorer {
        $required = [];
        $optional = [];
        $excluded = [];

        foreach ($query->clauses as $clause) {
            $scorer = $this->create($clause->query, $postingLists, $statistics, $universe);

            if ($clause->occur === Occur::Must) {
                $required[] = $scorer;
            } elseif ($clause->occur === Occur::Should) {
                $optional[] = $scorer;
            } else {
                $excluded[] = $scorer;
            }
        }

        if ($this->hasEmpty($required)) {
            return new EmptyScorer();
        }

        $scorer = $this->combine($query, $required, $optional, $universe);

        if ($excluded === []) {
            return $scorer;
        }

        return new ExclusionScorer(
            $scorer,
            count($excluded) === 1 ? $excluded[0] : new DisjunctionScorer($excluded, 1, false),
        );
    }

    private function combine(BooleanQuery $query, array $required, array $optional, ?DocumentSet $universe): Scorer
    {
        $optional = array_values(array_filter(
            $optional,
            static fn (Scorer $scorer): bool => !$scorer instanceof EmptyScorer,
        ));

        if ($required !== []) {
            $mandatory = count($required) === 1 ? $required[0] : new ConjunctionScorer($required);

            return $optional === [] ? $mandatory : new RequiredOptionalScorer($mandatory, $optional);
        }

        if ($optional === []) {
            return $this->matchAll($universe);
        }

        if (count($optional) === 1) {
            return $optional[0];
        }

        return new DisjunctionScorer(
            $optional,
            max($query->minimumShouldMatch, 1),
            $this->ranking->coordination,
        );
    }

    private function matchAll(?DocumentSet $universe): Scorer
    {
        if ($universe === null || $universe->isEmpty()) {
            return new EmptyScorer();
        }

        return new ConstantScoreScorer($universe->iterator(), 1.0, 'match all documents');
    }

    private function hasEmpty(array $scorers): bool
    {
        foreach ($scorers as $scorer) {
            if ($scorer instanceof EmptyScorer) {
                return true;
            }
        }

        return false;
    }
}
