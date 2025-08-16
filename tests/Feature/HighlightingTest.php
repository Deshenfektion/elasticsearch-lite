<?php

declare(strict_types=1);

namespace EsLite\Tests\Feature;

use EsLite\Highlight\HighlightOptions;
use EsLite\Highlight\MatchLocator;
use EsLite\Highlight\PassageBuilder;
use EsLite\Tests\Support\EngineTestCase;

final class HighlightingTest extends EngineTestCase
{
    public function testWrapsMatchedTermsInTheConfiguredTags(): void
    {
        $this->index($this->document('doc-1', 'Inverted index', 'An inverted index maps terms to documents.'));

        $fragments = $this->search('inverted')->hits[0]->highlights['body'];

        self::assertStringContainsString('<mark>inverted</mark>', $fragments[0]);
    }

    public function testHighlightsEveryMatchInAFragment(): void
    {
        $this->index($this->document('doc-1', 'Repeats', 'index and index and index again'));

        $fragments = $this->search('index')->hits[0]->highlights['body'];

        self::assertSame(3, substr_count($fragments[0], '<mark>'));
    }

    public function testHighlightsTheStemmedFormWithItsOriginalSpelling(): void
    {
        $this->index($this->document('doc-1', 'Stemming', 'Documents are indexed and indexing is fast.'));

        $fragments = $this->search('indexing')->hits[0]->highlights['body'];

        self::assertStringContainsString('<mark>indexed</mark>', $fragments[0]);
        self::assertStringContainsString('<mark>indexing</mark>', $fragments[0]);
    }

    public function testHighlightsTitleMatchesSeparately(): void
    {
        $this->index($this->document('doc-1', 'Ranking with BM25', 'Body text without the query term.'));

        $highlights = $this->search('bm25')->hits[0]->highlights;

        self::assertStringContainsString('<mark>BM25</mark>', $highlights['title'][0]);
    }

    public function testPhraseHighlightSpansTheWholePhrase(): void
    {
        $this->index($this->document('doc-1', 'Phrases', 'A posting list is sorted by document identifier.'));

        $fragments = $this->search('"posting list"')->hits[0]->highlights['body'];

        self::assertStringContainsString('<mark>posting list</mark>', $fragments[0]);
    }

    public function testProducesMultipleFragmentsForLongDocuments(): void
    {
        $body = str_repeat('filler words to push the matches apart. ', 12)
            . 'first mention of skiplists. '
            . str_repeat('more filler words in between the two matches. ', 12)
            . 'second mention of skiplists.';

        $this->index($this->document('doc-1', 'Long document', $body));
        $fragments = $this->search('skiplists')->hits[0]->highlights['body'];

        self::assertGreaterThan(1, count($fragments));
        self::assertStringContainsString('<mark>skiplists</mark>', $fragments[0]);
        self::assertStringContainsString('<mark>skiplists</mark>', $fragments[1]);
    }

    public function testFragmentsAreShorterThanTheDocument(): void
    {
        $body = str_repeat('a long body that keeps going and going. ', 40) . 'needle at the end.';
        $this->index($this->document('doc-1', 'Long document', $body));

        $fragments = $this->search('needle')->hits[0]->highlights['body'];

        self::assertLessThan(strlen($body) / 2, strlen($fragments[0]));
        self::assertStringStartsWith('…', $fragments[0]);
    }

    public function testFallsBackToALeadingExcerptWhenNothingMatchesInTheBody(): void
    {
        $this->index($this->document('doc-1', 'Tokenizers', 'This body never mentions the query term at all.'));

        $fragments = $this->search('tokenizers')->hits[0]->highlights['body'];

        self::assertStringNotContainsString('<mark>', $fragments[0]);
        self::assertStringStartsWith('This body never mentions', $fragments[0]);
    }

    public function testEscapesMarkupFoundInTheStoredText(): void
    {
        $this->index($this->document('doc-1', 'Escaping', 'A needle inside <script>alert(1)</script> markup.'));

        $fragments = $this->search('needle')->hits[0]->highlights['body'];

        self::assertStringContainsString('&lt;script&gt;', $fragments[0]);
        self::assertStringNotContainsString('<script>', $fragments[0]);
    }

    public function testHighlightingCanBeDisabled(): void
    {
        $this->index($this->document('doc-1', 'Inverted index', 'An inverted index maps terms.'));

        self::assertSame([], $this->search('inverted', ['highlight' => '0'])->hits[0]->highlights);
    }

    public function testCustomTagsAreHonoured(): void
    {
        $locator = new MatchLocator($this->app->analyzer());
        $builder = new PassageBuilder();
        $options = (new HighlightOptions())->withTags('[', ']');
        $text = 'an inverted index is a map';
        $spans = $locator->locate($text, ['invert']);
        $passages = $builder->build($text, $spans, $options);

        self::assertNotSame([], $spans);
        self::assertNotSame([], $passages);
    }

    public function testLocatorFindsPhraseAndTermSpans(): void
    {
        $locator = new MatchLocator($this->app->analyzer());
        $text = 'a posting list and another posting somewhere';

        $spans = $locator->locate($text, ['post'], [['post', 'list']]);
        $phraseSpans = array_values(array_filter($spans, static fn ($span): bool => $span->phrase));

        self::assertCount(1, $phraseSpans);
        self::assertSame('posting list', substr($text, $phraseSpans[0]->start, $phraseSpans[0]->end - $phraseSpans[0]->start));
    }

    public function testLocatorReturnsNothingWithoutQueryTerms(): void
    {
        $locator = new MatchLocator($this->app->analyzer());

        self::assertSame([], $locator->locate('some text', []));
        self::assertSame([], $locator->locate('', ['text']));
    }

    public function testPassageScoringPrefersWindowsWithMoreDistinctTerms(): void
    {
        $locator = new MatchLocator($this->app->analyzer());
        $builder = new PassageBuilder();
        $options = new HighlightOptions('<mark>', '</mark>', 60, 1);

        $text = 'alpha appears alone here. ' . str_repeat('padding words. ', 8) . 'alpha and beta appear together.';
        $spans = $locator->locate($text, ['alpha', 'beta']);
        $passages = $builder->build($text, $spans, $options);

        self::assertCount(1, $passages);
        self::assertSame(2, $passages[0]->distinctTerms());
    }
}
