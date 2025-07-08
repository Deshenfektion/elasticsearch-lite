<?php

declare(strict_types=1);

namespace EsLite\Tests\Unit\Analysis;

use EsLite\Analysis\StandardTokenizer;
use PHPUnit\Framework\TestCase;

final class StandardTokenizerTest extends TestCase
{
    private StandardTokenizer $tokenizer;

    protected function setUp(): void
    {
        $this->tokenizer = new StandardTokenizer();
    }

    public function testSplitsOnWhitespaceAndPunctuation(): void
    {
        $stream = $this->tokenizer->tokenize('Inverted index, postings; and skip-lists.');

        self::assertSame(['Inverted', 'index', 'postings', 'and', 'skip', 'lists'], $stream->terms());
    }

    public function testAssignsConsecutivePositions(): void
    {
        $positions = [];

        foreach ($this->tokenizer->tokenize('one two three') as $token) {
            $positions[] = $token->position;
        }

        self::assertSame([0, 1, 2], $positions);
    }

    public function testRecordsByteOffsetsForHighlighting(): void
    {
        $text = 'the quick brown fox';
        $tokens = $this->tokenizer->tokenize($text)->tokens();

        self::assertSame('quick', substr($text, $tokens[1]->startOffset, $tokens[1]->length()));
        self::assertSame('fox', substr($text, $tokens[3]->startOffset, $tokens[3]->length()));
    }

    public function testKeepsDecimalNumbersTogether(): void
    {
        self::assertSame(['PHP', '8.4', 'released', '2024'], $this->tokenizer->tokenize('PHP 8.4 released 2024')->terms());
    }

    public function testKeepsApostrophesInsideWords(): void
    {
        self::assertSame(["don't", 'stop'], $this->tokenizer->tokenize("don't stop")->terms());
    }

    public function testHandlesUnicodeLetters(): void
    {
        self::assertSame(['Müller', 'straße', 'café'], $this->tokenizer->tokenize('Müller straße café')->terms());
    }

    public function testReturnsEmptyStreamForTextWithoutWords(): void
    {
        self::assertTrue($this->tokenizer->tokenize('--- ... ///')->isEmpty());
        self::assertTrue($this->tokenizer->tokenize('')->isEmpty());
    }

    public function testCountsFrequenciesAndPositionsPerTerm(): void
    {
        $stream = $this->tokenizer->tokenize('index index index');

        self::assertSame(['index' => 3], $stream->frequencies());
        self::assertSame(['index' => [0, 1, 2]], $stream->positions());
    }
}
