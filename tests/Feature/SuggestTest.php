<?php

declare(strict_types=1);

namespace EsLite\Tests\Feature;

use EsLite\Tests\Support\EngineTestCase;

final class SuggestTest extends EngineTestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCorpus();
    }

    public function testCompletesTermsFromTheDictionary(): void
    {
        $suggestions = $this->app->suggestService()->suggest('rank');

        self::assertSame('rank', $suggestions['terms'][0]['term']);
        self::assertGreaterThan(0, $suggestions['terms'][0]['documents']);
    }

    public function testOrdersCompletionsByDocumentFrequency(): void
    {
        $this->index($this->document('extra-1', 'Indigo', 'indigo dye'));
        $frequencies = array_column($this->app->suggestService()->suggest('ind')['terms'], 'documents');
        $sorted = $frequencies;
        rsort($sorted);

        self::assertGreaterThan(1, count($frequencies));
        self::assertSame($sorted, $frequencies);
    }

    public function testSuggestionsAreCappedBySize(): void
    {
        self::assertLessThanOrEqual(2, count($this->app->suggestService()->suggest('i', 2)['terms']));
    }

    public function testShortPrefixesAreIgnored(): void
    {
        self::assertSame([], $this->app->suggestService()->suggest('i')['terms']);
    }

    public function testCompletionKeepsAlreadyTypedWords(): void
    {
        $suggestions = $this->app->suggestService()->suggest('ranking mod');

        self::assertNotSame([], $suggestions['terms']);
        self::assertStringStartsWith('ranking ', $suggestions['terms'][0]['query']);
    }

    public function testPopularQueriesComeFromTheSearchLog(): void
    {
        $this->search('inverted index');
        $this->search('inverted index');
        $this->search('ranking models');

        $suggestions = $this->app->suggestService()->suggest('inv');

        self::assertSame('inverted index', $suggestions['queries'][0]['query']);
        self::assertSame(2, $suggestions['queries'][0]['searches']);
    }

    public function testQueriesWithoutHitsAreNotSuggested(): void
    {
        $this->search('kubernetes');

        self::assertSame([], $this->app->suggestService()->suggest('kube')['queries']);
    }

    public function testCompletionsHelperReturnsPlainTerms(): void
    {
        self::assertContains('index', $this->app->suggestService()->completions('ind', 5));
    }

    public function testPrefixIsNormalisedBeforeLookup(): void
    {
        self::assertNotSame([], $this->app->suggestService()->suggest('IND')['terms']);
    }
}
