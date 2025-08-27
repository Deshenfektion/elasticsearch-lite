<?php

declare(strict_types=1);

namespace EsLite\Tests\Unit\Query;

use EsLite\Query\Exception\QueryParseException;
use EsLite\Query\Node\BooleanQuery;
use EsLite\Query\Node\MatchAllQuery;
use EsLite\Query\Node\PhraseQuery;
use EsLite\Query\Node\PrefixQuery;
use EsLite\Query\Node\TermQuery;
use EsLite\Query\Node\WildcardQuery;
use EsLite\Query\Occur;
use EsLite\Query\QueryParser;
use PHPUnit\Framework\TestCase;

final class QueryParserTest extends TestCase
{
    private QueryParser $parser;

    protected function setUp(): void
    {
        $this->parser = new QueryParser();
    }

    public function testParsesSingleTerm(): void
    {
        $query = $this->parser->parse('index');

        self::assertInstanceOf(TermQuery::class, $query);
        self::assertSame('index', $query->term);
        self::assertNull($query->field);
    }

    public function testDefaultsToShouldClausesForBareTerms(): void
    {
        $query = $this->parser->parse('inverted index');

        self::assertInstanceOf(BooleanQuery::class, $query);
        self::assertCount(2, $query->clauses);
        self::assertSame(Occur::Should, $query->clauses[0]->occur);
        self::assertSame(1, $query->minimumShouldMatch);
    }

    public function testDefaultOperatorCanBeAnd(): void
    {
        $query = QueryParser::withDefaultOperator('and')->parse('inverted index');

        self::assertInstanceOf(BooleanQuery::class, $query);
        self::assertSame(Occur::Must, $query->clauses[0]->occur);
        self::assertSame(Occur::Must, $query->clauses[1]->occur);
    }

    public function testPlusMakesAClauseRequired(): void
    {
        $query = $this->parser->parse('+index ranking');

        self::assertSame(Occur::Must, $query->clauses[0]->occur);
        self::assertSame(Occur::Should, $query->clauses[1]->occur);
        self::assertSame(0, $query->minimumShouldMatch);
    }

    public function testMinusAndNotProhibitAClause(): void
    {
        foreach (['index -ranking', 'index NOT ranking', 'index !ranking'] as $input) {
            $query = $this->parser->parse($input);

            self::assertSame(Occur::MustNot, $query->clauses[1]->occur, $input);
        }
    }

    public function testExplicitAndTightensBothSides(): void
    {
        $query = $this->parser->parse('index AND ranking');

        self::assertSame(Occur::Must, $query->clauses[0]->occur);
        self::assertSame(Occur::Must, $query->clauses[1]->occur);
    }

    public function testExplicitOrRelaxesTheLeftSide(): void
    {
        $query = QueryParser::withDefaultOperator('and')->parse('index OR ranking');

        self::assertSame(Occur::Should, $query->clauses[0]->occur);
        self::assertSame(Occur::Should, $query->clauses[1]->occur);
    }

    public function testParsesQuotedPhrase(): void
    {
        $query = $this->parser->parse('"posting list"');

        self::assertInstanceOf(PhraseQuery::class, $query);
        self::assertSame(['posting', 'list'], $query->terms);
        self::assertSame([0, 1], $query->offsets);
    }

    public function testParsesEscapedQuoteInsidePhrase(): void
    {
        $query = $this->parser->parse('"a \\" b"');

        self::assertInstanceOf(PhraseQuery::class, $query);
        self::assertSame(['a', '"', 'b'], $query->terms);
    }

    public function testParsesFieldRestrictedTerm(): void
    {
        $query = $this->parser->parse('title:bm25');

        self::assertInstanceOf(TermQuery::class, $query);
        self::assertSame('title', $query->field);
        self::assertSame('bm25', $query->term);
    }

    public function testParsesFieldRestrictedPhrase(): void
    {
        $query = $this->parser->parse('title:"inverted index"');

        self::assertInstanceOf(PhraseQuery::class, $query);
        self::assertSame('title', $query->field);
    }

    public function testAppliesFieldToEveryClauseInAGroup(): void
    {
        $query = $this->parser->parse('title:(bm25 OR tfidf)');

        self::assertInstanceOf(BooleanQuery::class, $query);

        foreach ($query->clauses as $clause) {
            self::assertSame('title', $clause->query->field());
        }
    }

    public function testAppliesFieldToPhrasesAndPrefixesInsideAGroup(): void
    {
        $query = $this->parser->parse('title:("posting list" OR rank* OR st?m)');

        self::assertInstanceOf(BooleanQuery::class, $query);
        self::assertInstanceOf(PhraseQuery::class, $query->clauses[0]->query);
        self::assertInstanceOf(PrefixQuery::class, $query->clauses[1]->query);
        self::assertInstanceOf(WildcardQuery::class, $query->clauses[2]->query);

        foreach ($query->clauses as $clause) {
            self::assertSame('title', $clause->query->field());
        }
    }

    public function testParsesPrefixQuery(): void
    {
        $query = $this->parser->parse('rank*');

        self::assertInstanceOf(PrefixQuery::class, $query);
        self::assertSame('rank', $query->prefix);
    }

    public function testParsesWildcardQuery(): void
    {
        self::assertInstanceOf(WildcardQuery::class, $this->parser->parse('st?m'));
        self::assertInstanceOf(WildcardQuery::class, $this->parser->parse('to*n'));
    }

    public function testBareAsteriskMatchesEverything(): void
    {
        self::assertInstanceOf(MatchAllQuery::class, $this->parser->parse('*'));
    }

    public function testEmptyQueryMatchesEverything(): void
    {
        self::assertInstanceOf(MatchAllQuery::class, $this->parser->parse('   '));
    }

    public function testKeepsHyphenatedWordsTogether(): void
    {
        $query = $this->parser->parse('e-mail');

        self::assertInstanceOf(TermQuery::class, $query);
        self::assertSame('e-mail', $query->term);
    }

    public function testNestedGroupsProduceNestedBooleans(): void
    {
        $query = $this->parser->parse('ranking AND (bm25 OR tfidf)');

        self::assertInstanceOf(BooleanQuery::class, $query);
        self::assertSame(Occur::Must, $query->clauses[0]->occur);
        self::assertInstanceOf(BooleanQuery::class, $query->clauses[1]->query);
    }

    public function testDescribeRoundTripsStructure(): void
    {
        self::assertSame('(+index -rank title:bm25)', $this->parser->parse('+index -rank title:bm25')->describe());
        self::assertSame('"posting list"', $this->parser->parse('"posting list"')->describe());
    }

    public function testSerialisesToArray(): void
    {
        self::assertSame(
            ['type' => 'term', 'field' => 'title', 'term' => 'bm25', 'boost' => 1.0],
            $this->parser->parse('title:bm25')->toArray(),
        );
    }

    public function testRejectsUnterminatedPhrase(): void
    {
        $this->expectException(QueryParseException::class);
        $this->parser->parse('"posting list');
    }

    public function testRejectsUnbalancedParenthesis(): void
    {
        $this->expectException(QueryParseException::class);
        $this->parser->parse('(index OR rank');
    }

    public function testRejectsEmptyGroup(): void
    {
        $this->expectException(QueryParseException::class);
        $this->parser->parse('index AND ()');
    }

    public function testRejectsOverlongQueries(): void
    {
        $this->expectException(QueryParseException::class);
        $this->parser->parse(str_repeat('term ', 200));
    }
}
