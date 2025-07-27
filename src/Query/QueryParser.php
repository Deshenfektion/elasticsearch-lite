<?php

declare(strict_types=1);

namespace EsLite\Query;

use EsLite\Query\Exception\QueryParseException;
use EsLite\Query\Node\BooleanClause;
use EsLite\Query\Node\BooleanQuery;
use EsLite\Query\Node\MatchAllQuery;
use EsLite\Query\Node\PhraseQuery;
use EsLite\Query\Node\PrefixQuery;
use EsLite\Query\Node\Query;
use EsLite\Query\Node\TermQuery;
use EsLite\Query\Node\WildcardQuery;

final class QueryParser
{
    private const int MAX_DEPTH = 12;

    private array $tokens = [];

    private int $cursor = 0;

    public function __construct(
        private readonly Occur $defaultOccur = Occur::Should,
        private readonly Lexer $lexer = new Lexer(),
    ) {
    }

    public static function withDefaultOperator(string $operator): self
    {
        return new self(strtolower($operator) === 'and' ? Occur::Must : Occur::Should);
    }

    public function parse(string $input): Query
    {
        $this->tokens = $this->lexer->tokenize($input);
        $this->cursor = 0;

        if ($this->peek()->is(TokenType::End)) {
            return new MatchAllQuery();
        }

        $query = $this->parseBoolean(0);
        $trailing = $this->peek();

        if (!$trailing->is(TokenType::End)) {
            throw $trailing->is(TokenType::RightParen)
                ? QueryParseException::unbalancedParenthesis($trailing->position)
                : QueryParseException::unexpectedToken($trailing->value, $trailing->position);
        }

        return $query;
    }

    private function parseBoolean(int $depth): Query
    {
        if ($depth > self::MAX_DEPTH) {
            throw QueryParseException::tooDeep(self::MAX_DEPTH);
        }

        $clauses = [];
        $pendingOccur = null;

        while (!$this->peek()->is(TokenType::End, TokenType::RightParen)) {
            $token = $this->peek();

            if ($token->is(TokenType::Or)) {
                $this->advance();
                $pendingOccur = Occur::Should;
                $this->relaxPrevious($clauses, Occur::Should);

                continue;
            }

            if ($token->is(TokenType::And)) {
                $this->advance();
                $pendingOccur = Occur::Must;
                $this->tightenPrevious($clauses);

                continue;
            }

            $clause = $this->parseClause($depth, $pendingOccur);
            $clauses[] = $clause;
            $pendingOccur = null;
        }

        if ($clauses === []) {
            throw QueryParseException::emptyGroup($this->peek()->position);
        }

        if (count($clauses) === 1 && $clauses[0]->occur !== Occur::MustNot) {
            return $clauses[0]->query;
        }

        return new BooleanQuery($clauses, $this->minimumShouldMatch($clauses));
    }

    private function parseClause(int $depth, ?Occur $pendingOccur): BooleanClause
    {
        $occur = $pendingOccur ?? $this->defaultOccur;
        $token = $this->peek();

        if ($token->is(TokenType::Plus)) {
            $this->advance();
            $occur = Occur::Must;
        } elseif ($token->is(TokenType::Minus, TokenType::Not)) {
            $this->advance();
            $occur = Occur::MustNot;
        }

        return new BooleanClause($occur, $this->parsePrimary($depth));
    }

    private function parsePrimary(int $depth): Query
    {
        $token = $this->peek();

        if ($token->is(TokenType::LeftParen)) {
            $this->advance();
            $query = $this->parseBoolean($depth + 1);

            if (!$this->peek()->is(TokenType::RightParen)) {
                throw QueryParseException::unbalancedParenthesis($token->position);
            }

            $this->advance();

            return $query;
        }

        if ($token->is(TokenType::Phrase)) {
            $this->advance();

            return new PhraseQuery($this->phraseTerms($token->value));
        }

        if (!$token->is(TokenType::Word)) {
            throw QueryParseException::unexpectedToken($token->value, $token->position);
        }

        $this->advance();

        if ($this->peek()->is(TokenType::Colon)) {
            $this->advance();

            return $this->parseFielded($token->value, $depth);
        }

        return $this->leaf($token->value, null);
    }

    private function parseFielded(string $field, int $depth): Query
    {
        $token = $this->peek();

        if ($token->is(TokenType::Phrase)) {
            $this->advance();

            return new PhraseQuery($this->phraseTerms($token->value), $field);
        }

        if ($token->is(TokenType::LeftParen)) {
            $this->advance();
            $group = $this->parseBoolean($depth + 1);

            if (!$this->peek()->is(TokenType::RightParen)) {
                throw QueryParseException::unbalancedParenthesis($token->position);
            }

            $this->advance();

            return $this->applyField($group, $field);
        }

        if (!$token->is(TokenType::Word)) {
            throw QueryParseException::unexpectedToken($token->value, $token->position);
        }

        $this->advance();

        return $this->leaf($token->value, $field);
    }

    private function leaf(string $value, ?string $field): Query
    {
        if (str_contains($value, '?') || str_contains(rtrim($value, '*'), '*')) {
            return new WildcardQuery($value, $field);
        }

        if (str_ends_with($value, '*')) {
            $prefix = rtrim($value, '*');

            return $prefix === '' ? new MatchAllQuery() : new PrefixQuery($prefix, $field);
        }

        return new TermQuery($value, $field);
    }

    private function applyField(Query $query, string $field): Query
    {
        return match (true) {
            $query instanceof TermQuery => new TermQuery($query->term, $field, $query->boost),
            $query instanceof PhraseQuery => new PhraseQuery($query->terms, $field, $query->slop, $query->boost),
            $query instanceof PrefixQuery => new PrefixQuery($query->prefix, $field, $query->boost),
            $query instanceof WildcardQuery => new WildcardQuery($query->pattern, $field, $query->boost),
            $query instanceof BooleanQuery => $query->withClauses(array_map(
                fn (BooleanClause $clause): BooleanClause => $clause->withQuery(
                    $this->applyField($clause->query, $field),
                ),
                $query->clauses,
            )),
            default => $query,
        };
    }

    private function phraseTerms(string $phrase): array
    {
        return array_values(array_filter(preg_split('/\s+/u', trim($phrase)) ?: []));
    }

    private function relaxPrevious(array &$clauses, Occur $occur): void
    {
        $last = array_key_last($clauses);

        if ($last === null) {
            return;
        }

        if ($clauses[$last]->occur === Occur::Must) {
            $clauses[$last] = new BooleanClause($occur, $clauses[$last]->query);
        }
    }

    private function tightenPrevious(array &$clauses): void
    {
        $last = array_key_last($clauses);

        if ($last === null) {
            return;
        }

        if ($clauses[$last]->occur === Occur::Should) {
            $clauses[$last] = BooleanClause::must($clauses[$last]->query);
        }
    }

    private function minimumShouldMatch(array $clauses): int
    {
        foreach ($clauses as $clause) {
            if ($clause->occur === Occur::Must) {
                return 0;
            }
        }

        return 1;
    }

    private function peek(): Token
    {
        return $this->tokens[$this->cursor] ?? new Token(TokenType::End, '', 0);
    }

    private function advance(): Token
    {
        $token = $this->peek();
        $this->cursor++;

        return $token;
    }
}
