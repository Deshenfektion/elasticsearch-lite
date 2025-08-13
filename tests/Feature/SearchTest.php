<?php

declare(strict_types=1);

namespace EsLite\Tests\Feature;

use EsLite\Query\Exception\QueryParseException;
use EsLite\Tests\Support\EngineTestCase;

final class SearchTest extends EngineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCorpus();
    }

    public function testSingleTermMatchesStemmedForms(): void
    {
        $response = $this->search('indexes');

        self::assertGreaterThan(0, $response->total);
        self::assertContains('search-basics', $this->titles($response));
    }

    public function testDocumentsMatchingMoreClausesRankHigher(): void
    {
        $response = $this->search('inverted index');

        self::assertSame('search-basics', $this->titles($response)[0]);
    }

    public function testTitleMatchesOutrankBodyMatches(): void
    {
        $this->index($this->document('title-hit', 'Tokenizers everywhere', 'unrelated prose about nothing'));
        $this->index($this->document('body-hit', 'Something else', 'a passing mention of tokenizers in the body'));

        $titles = $this->titles($this->search('tokenizers'));

        self::assertLessThan(array_search('body-hit', $titles, true), array_search('title-hit', $titles, true));
    }

    public function testAndRequiresEveryTerm(): void
    {
        self::assertSame(['ranking-models'], $this->titles($this->search('ranking AND bm25')));
        self::assertSame(0, $this->search('ranking AND tokenization')->total);
    }

    public function testOrMatchesEitherTerm(): void
    {
        self::assertGreaterThanOrEqual(2, $this->search('bm25 OR tokenization')->total);
    }

    public function testNotExcludesDocuments(): void
    {
        $titles = $this->titles($this->search('index -posting'));

        self::assertContains('search-basics', $titles);
        self::assertNotContains('posting-lists', $titles);
    }

    public function testRequiredAndProhibitedClausesCombine(): void
    {
        $titles = $this->titles($this->search('+index -posting ranking'));

        self::assertNotContains('posting-lists', $titles);
        self::assertContains('search-basics', $titles);
    }

    public function testPhraseQueryRequiresAdjacentTerms(): void
    {
        self::assertSame(['posting-lists'], $this->titles($this->search('"posting list"')));
        self::assertSame(0, $this->search('"list posting"')->total);
    }

    public function testPhraseQueryOutranksLooseMatchOfTheSameTerms(): void
    {
        $this->index($this->document('loose', 'Loose words', 'a list of things and a posting somewhere later'));
        $titles = $this->titles($this->search('"posting list"'));

        self::assertSame('posting-lists', $titles[0]);
    }

    public function testFieldRestrictedTermOnlyMatchesThatField(): void
    {
        self::assertSame(['ranking-models'], $this->titles($this->search('title:ranking')));
        self::assertSame(0, $this->search('title:sorted')->total);
    }

    public function testPrefixQueryExpandsAgainstTheDictionary(): void
    {
        $this->index($this->document('indigo', 'Indigo', 'a document about indigo dye'));
        $response = $this->search('ind*');

        self::assertGreaterThanOrEqual(2, $response->total);
        self::assertStringContainsString('ind* ->', $response->query['rewritten']);
        self::assertContains('indigo', $response->query['terms']);
    }

    public function testPrefixWithASingleExpansionCollapsesToATermQuery(): void
    {
        $response = $this->search('token*');

        self::assertSame(['tokenizers'], $this->titles($response));
        self::assertSame('token', $response->query['rewritten']);
    }

    public function testWildcardQueryMatchesIndexedTerms(): void
    {
        self::assertSame(['tokenizers'], $this->titles($this->search('to*n')));
    }

    public function testUnknownTermsReturnNoHits(): void
    {
        $response = $this->search('kubernetes');

        self::assertSame(0, $response->total);
        self::assertSame([], $response->hits);
    }

    public function testStopWordOnlyQueryReturnsNothing(): void
    {
        self::assertSame(0, $this->search('the and of')->total);
    }

    public function testEmptyQueryBrowsesEverything(): void
    {
        $response = $this->search('');

        self::assertSame(4, $response->total);
    }

    public function testPaginationSlicesTheRankedList(): void
    {
        $first = $this->search('index OR ranking OR tokenization OR posting', ['size' => 2, 'page' => 1]);
        $second = $this->search('index OR ranking OR tokenization OR posting', ['size' => 2, 'page' => 2]);

        self::assertCount(2, $first->hits);
        self::assertSame(1, $first->page);
        self::assertSame(2, $second->page);
        self::assertSame($first->total, $second->total);
        self::assertSame([], array_intersect($this->titles($first), $this->titles($second)));
    }

    public function testSizeIsCappedByConfiguration(): void
    {
        $response = $this->search('index', ['size' => 5000]);

        self::assertSame($this->app->config()->int('app.search.max_size'), $response->size);
    }

    public function testSortingByDateIgnoresRelevance(): void
    {
        $newest = $this->search('', ['sort' => 'newest']);
        $oldest = $this->search('', ['sort' => 'oldest']);

        self::assertSame('tokenizers', $this->titles($newest)[0]);
        self::assertSame('search-basics', $this->titles($oldest)[0]);
    }

    public function testResponseDescribesTheExecutedQuery(): void
    {
        $response = $this->search('inverted index');

        self::assertSame('(invert index)', $response->query['rewritten']);
        self::assertContains('index', $response->query['terms']);
        self::assertFalse($response->query['positions_loaded']);
    }

    public function testPositionsAreOnlyLoadedForPhraseQueries(): void
    {
        self::assertTrue($this->search('"posting list"')->query['positions_loaded']);
        self::assertFalse($this->search('posting list')->query['positions_loaded']);
    }

    public function testExplanationValueMatchesTheHitScore(): void
    {
        $response = $this->search('inverted index', ['explain' => '1']);
        $hit = $response->hits[0];

        self::assertNotNull($hit->explanation);
        self::assertEqualsWithDelta($hit->score, $hit->explanation->value, 0.0001);
        self::assertNotSame([], $hit->explanation->details);
    }

    public function testInvalidQuerySyntaxIsReported(): void
    {
        $this->expectException(QueryParseException::class);
        $this->search('"unterminated');
    }

    public function testUnknownFieldIsReported(): void
    {
        $this->expectException(QueryParseException::class);
        $this->search('category:Guides');
    }

    public function testSearchesAreLogged(): void
    {
        $this->search('inverted index');
        $statistics = $this->app->searchLogs()->statistics();

        self::assertSame(1, $statistics['total']);
    }

    public function testTfidfAndBm25BothRankTheObviousDocumentFirst(): void
    {
        $this->boot(['app.ranking.model' => 'tfidf']);
        $this->seedCorpus();

        $response = $this->search('inverted index');

        self::assertSame('search-basics', $this->titles($response)[0]);
        self::assertSame('tfidf', $this->app->ranking()->model->name());
    }
}
