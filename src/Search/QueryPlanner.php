<?php

declare(strict_types=1);

namespace EsLite\Search;

use EsLite\Analysis\Analyzer;
use EsLite\Index\IndexReader;
use EsLite\Index\TermInfo;
use EsLite\Query\Exception\QueryParseException;
use EsLite\Query\Node\BooleanClause;
use EsLite\Query\Node\BooleanQuery;
use EsLite\Query\Node\ExpandedQuery;
use EsLite\Query\Node\MatchAllQuery;
use EsLite\Query\Node\MatchNoneQuery;
use EsLite\Query\Node\PhraseQuery;
use EsLite\Query\Node\PrefixQuery;
use EsLite\Query\Node\Query;
use EsLite\Query\Node\TermQuery;
use EsLite\Query\Node\WildcardQuery;
use EsLite\Query\Occur;

final class QueryPlanner
{
    public function __construct(
        private readonly IndexReader $reader,
        private readonly Analyzer $analyzer,
        private readonly int $maxExpansions = 64,
    ) {
    }

    public function plan(Query $query): PlannedQuery
    {
        $rewritten = $this->rewrite($query);
        $terms = [];
        $phrases = [];
        $this->collect($rewritten, $terms, $phrases);

        return new PlannedQuery($query, $rewritten, array_values(array_unique($terms)), $phrases !== [], $phrases);
    }

    private function rewrite(Query $query): Query
    {
        return match (true) {
            $query instanceof TermQuery => $this->rewriteTerm($query),
            $query instanceof PhraseQuery => $this->rewritePhrase($query),
            $query instanceof PrefixQuery => $this->rewritePrefix($query),
            $query instanceof WildcardQuery => $this->rewriteWildcard($query),
            $query instanceof BooleanQuery => $this->rewriteBoolean($query),
            default => $query,
        };
    }

    private function rewriteTerm(TermQuery $query): Query
    {
        $this->assertField($query->field);
        $terms = $this->analyzer->terms($query->term);

        if ($terms === []) {
            return new MatchNoneQuery();
        }

        if (count($terms) === 1) {
            return $query->withTerm($terms[0]);
        }

        return new BooleanQuery(array_map(
            static fn (string $term): BooleanClause => BooleanClause::must(new TermQuery($term, $query->field)),
            $terms,
        ), 0);
    }

    private function rewritePhrase(PhraseQuery $query): Query
    {
        $this->assertField($query->field);
        $stream = $this->analyzer->analyse(implode(' ', $query->terms));
        $tokens = $stream->tokens();

        if ($tokens === []) {
            return new MatchNoneQuery();
        }

        $base = $tokens[0]->position;
        $terms = [];
        $offsets = [];

        foreach ($tokens as $token) {
            $terms[] = $token->term;
            $offsets[] = $token->position - $base;
        }

        if (count($terms) === 1) {
            return new TermQuery($terms[0], $query->field, $query->boost);
        }

        return $query->withTerms($terms, $offsets);
    }

    private function rewritePrefix(PrefixQuery $query): Query
    {
        $this->assertField($query->field);
        $prefix = $this->analyzer->normaliseTerm($query->prefix);
        $expansions = $this->reader->dictionary()->expandPrefix($prefix, $this->maxExpansions + 1);

        return $this->expansion($expansions, $query->field, $query->prefix . '*');
    }

    private function rewriteWildcard(WildcardQuery $query): Query
    {
        $this->assertField($query->field);
        $pattern = $this->analyzer->normaliseTerm($query->pattern);
        $expansions = $this->reader->dictionary()->expandWildcard($pattern, $this->maxExpansions + 1);

        return $this->expansion($expansions, $query->field, $query->pattern);
    }

    private function rewriteBoolean(BooleanQuery $query): Query
    {
        $clauses = [];

        foreach ($query->clauses as $clause) {
            $rewritten = $this->rewrite($clause->query);

            if ($rewritten instanceof MatchNoneQuery && $clause->occur !== Occur::Must) {
                continue;
            }

            $clauses[] = $clause->withQuery($rewritten);
        }

        if ($clauses === []) {
            return new MatchNoneQuery();
        }

        if (count($clauses) === 1 && $clauses[0]->occur !== Occur::MustNot) {
            return $clauses[0]->query;
        }

        return $query->withClauses($clauses);
    }

    private function expansion(array $expansions, ?string $field, string $source): Query
    {
        $truncated = count($expansions) > $this->maxExpansions;
        $expansions = array_slice($expansions, 0, $this->maxExpansions);

        if ($expansions === []) {
            return new MatchNoneQuery();
        }

        $terms = array_map(static fn (TermInfo $info): string => $info->term, $expansions);

        if (count($terms) === 1) {
            return new TermQuery($terms[0], $field);
        }

        return new ExpandedQuery($terms, $field, $source, $truncated);
    }

    private function collect(Query $query, array &$terms, array &$phrases): void
    {
        if ($query instanceof TermQuery) {
            $terms[] = $query->term;

            return;
        }

        if ($query instanceof ExpandedQuery) {
            foreach ($query->terms as $term) {
                $terms[] = $term;
            }

            return;
        }

        if ($query instanceof PhraseQuery) {
            foreach ($query->terms as $term) {
                $terms[] = $term;
            }

            $phrases[] = $query->terms;

            return;
        }

        if ($query instanceof BooleanQuery) {
            foreach ($query->clauses as $clause) {
                $this->collect($clause->query, $terms, $phrases);
            }
        }
    }

    private function assertField(?string $field): void
    {
        if ($field === null || $this->reader->fields()->has($field)) {
            return;
        }

        throw QueryParseException::unknownField($field, $this->reader->fields()->names());
    }
}
