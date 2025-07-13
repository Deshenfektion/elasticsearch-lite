<?php

declare(strict_types=1);

namespace EsLite\Tests\Unit\Analysis;

use EsLite\Analysis\Analyzer;
use EsLite\Analysis\AnalyzerFactory;
use EsLite\Analysis\Filter\AsciiFoldingFilter;
use EsLite\Analysis\Filter\LengthFilter;
use EsLite\Analysis\Filter\LowercaseFilter;
use EsLite\Analysis\Filter\StopWordFilter;
use EsLite\Analysis\StandardTokenizer;
use EsLite\Analysis\StopWords;
use PHPUnit\Framework\TestCase;

final class AnalyzerTest extends TestCase
{
    private Analyzer $analyzer;

    protected function setUp(): void
    {
        $this->analyzer = AnalyzerFactory::standard();
    }

    public function testLowercasesFoldsAndStems(): void
    {
        self::assertSame(['cafe', 'document', 'rank'], $this->analyzer->terms('Café DOCUMENTS ranking'));
    }

    public function testRemovesStopWords(): void
    {
        self::assertSame(['quick', 'brown', 'fox'], $this->analyzer->terms('the quick and the brown fox'));
    }

    public function testKeepsPositionHolesWhereStopWordsWereRemoved(): void
    {
        $positions = [];

        foreach ($this->analyzer->analyse('bed and breakfast') as $token) {
            $positions[$token->term] = $token->position;
        }

        self::assertSame(['bed' => 0, 'breakfast' => 2], $positions);
    }

    public function testDropsTokensShorterThanTheMinimumLength(): void
    {
        self::assertSame(['index'], $this->analyzer->terms('a index'));
    }

    public function testStripsPossessiveApostrophes(): void
    {
        self::assertSame(['lucen', 'codec'], $this->analyzer->terms("Lucene's codecs"));
    }

    public function testOffsetsPointAtTheOriginalSpelling(): void
    {
        $text = 'Indexing documents quickly';
        $tokens = $this->analyzer->analyse($text)->tokens();

        self::assertSame('Indexing', substr($text, $tokens[0]->startOffset, $tokens[0]->length()));
        self::assertSame('index', $tokens[0]->term);
    }

    public function testNormaliseTermSkipsStemmingForPrefixQueries(): void
    {
        self::assertSame('indexing', $this->analyzer->normaliseTerm('Indexing'));
        self::assertSame('cafe', $this->analyzer->normaliseTerm('Café'));
    }

    public function testWithoutStemmingDropsOnlyTheStemFilter(): void
    {
        $unstemmed = $this->analyzer->withoutStemming();

        self::assertSame(['indexing', 'documents'], $unstemmed->terms('Indexing documents'));
        self::assertNotContains('stem:porter', $unstemmed->describe()['filters']);
    }

    public function testDescribesItsChain(): void
    {
        $description = $this->analyzer->describe();

        self::assertSame('standard', $description['tokenizer']);
        self::assertSame(
            ['apostrophe', 'lowercase', 'asciifolding', 'length', 'stop', 'stem:porter'],
            $description['filters'],
        );
    }

    public function testCustomChainCanSkipStemming(): void
    {
        $analyzer = new Analyzer(
            new StandardTokenizer(),
            new LowercaseFilter(),
            new AsciiFoldingFilter(),
            new LengthFilter(1, 20),
            new StopWordFilter(StopWords::english()),
        );

        self::assertSame(['better', 'queries'], $analyzer->terms('the better queries'));
        self::assertSame(['tokenizer'], $analyzer->terms('Tokenizer'));
    }

    public function testEmptyTextProducesNoTokens(): void
    {
        self::assertTrue($this->analyzer->analyse('   ')->isEmpty());
    }
}
