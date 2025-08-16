<?php

declare(strict_types=1);

namespace EsLite\Tests\Feature;

use EsLite\Search\Filter\CategoryFilter;
use EsLite\Search\Filter\FilterCompiler;
use EsLite\Search\Filter\FilterSet;
use EsLite\Search\Filter\TagFilter;
use EsLite\Tests\Support\EngineTestCase;

final class FilteringTest extends EngineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCorpus();
    }

    public function testCategoryFilterNarrowsResults(): void
    {
        $response = $this->search('', ['category' => 'Guides']);

        $titles = $this->titles($response);
        sort($titles);

        self::assertSame(2, $response->total);
        self::assertSame(['posting-lists', 'search-basics'], $titles);
    }

    public function testAuthorFilterNarrowsResults(): void
    {
        self::assertSame(2, $this->search('', ['author' => 'Ada'])->total);
        self::assertSame(1, $this->search('', ['author' => 'Cy'])->total);
    }

    public function testTagFilterMatchesAnyTagByDefault(): void
    {
        self::assertSame(3, $this->search('', ['tag' => 'index,ranking'])->total);
    }

    public function testTagFilterCanRequireEveryTag(): void
    {
        self::assertSame(1, $this->search('', ['tag' => 'index,postings', 'match_all_tags' => '1'])->total);
    }

    public function testDateRangeFilterRestrictsByPublicationDate(): void
    {
        self::assertSame(2, $this->search('', ['from' => '2025-03-01'])->total);
        self::assertSame(2, $this->search('', ['to' => '2025-02-28'])->total);
        self::assertSame(1, $this->search('', ['from' => '2025-02-01', 'to' => '2025-02-28'])->total);
    }

    public function testFiltersCombineWithFullTextRelevance(): void
    {
        $response = $this->search('index', ['category' => 'Guides']);

        self::assertGreaterThan(0, $response->total);
        self::assertGreaterThan(0, $response->hits[0]->score);

        foreach ($response->hits as $hit) {
            self::assertSame('Guides', $hit->document->category);
        }
    }

    public function testFiltersAreIntersectedWithEachOther(): void
    {
        self::assertSame(1, $this->search('', ['category' => 'Guides', 'author' => 'Ada', 'tag' => 'postings'])->total);
        self::assertSame(0, $this->search('', ['category' => 'Ranking', 'author' => 'Ada'])->total);
    }

    public function testFacetsCountMatchingDocumentsOnly(): void
    {
        $response = $this->search('index');

        self::assertArrayHasKey('categories', $response->facets);
        self::assertSame(2, $response->facets['categories']['Guides'] ?? 0);
        self::assertArrayHasKey('Ada', $response->facets['authors']);
    }

    public function testFacetsShrinkWhenAFilterIsApplied(): void
    {
        $unfiltered = $this->search('');
        $filtered = $this->search('', ['author' => 'Ada']);

        self::assertGreaterThan(count($filtered->facets['tags']), count($unfiltered->facets['tags']));
    }

    public function testFacetsCanBeDisabled(): void
    {
        self::assertSame([], $this->search('index', ['facets' => '0'])->facets);
    }

    public function testUnknownFilterValueYieldsNoResults(): void
    {
        self::assertSame(0, $this->search('', ['category' => 'Nonexistent'])->total);
    }

    public function testFilterSetCompilesToParameterisedSql(): void
    {
        $filters = new FilterSet(new CategoryFilter(['Guides']), new TagFilter(['index']));
        $compiled = $filters->compile($this->connection->dialect());

        self::assertStringContainsString('d.category_id IN', $compiled->where);
        self::assertStringContainsString('document_tags', $compiled->where);
        self::assertSame(['Guides', 'index'], $compiled->bindings);
    }

    public function testFilterSetIsBuiltFromRequestParameters(): void
    {
        $filters = FilterSet::fromArray([
            'category' => 'Guides,Ranking',
            'tag' => ['index'],
            'author' => 'Ada',
            'from' => '2025-01-01',
        ]);

        $description = $filters->toArray();

        self::assertSame(['Guides', 'Ranking'], $description['category']);
        self::assertSame(['index'], $description['tag']);
        self::assertSame(['Ada'], $description['author']);
        self::assertSame('2025-01-01', $description['published_from']);
    }

    public function testEmptyFilterSetCompilesToNothing(): void
    {
        $compiler = new FilterCompiler($this->connection, $this->app->documents());

        self::assertTrue(FilterSet::fromArray([])->isEmpty());
        self::assertNull($compiler->compile(FilterSet::fromArray([])));
        self::assertNull($compiler->compile(new FilterSet(new CategoryFilter([]))));
    }
}
