<?php

declare(strict_types=1);

namespace EsLite\Tests\Unit\Analysis;

use EsLite\Analysis\Stemmer\CachingStemmer;
use EsLite\Analysis\Stemmer\PorterStemmer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class PorterStemmerTest extends TestCase
{
    private PorterStemmer $stemmer;

    protected function setUp(): void
    {
        $this->stemmer = new PorterStemmer();
    }

    #[DataProvider('vocabulary')]
    public function testStemsKnownVocabulary(string $word, string $expected): void
    {
        self::assertSame($expected, $this->stemmer->stem($word));
    }

    public static function vocabulary(): array
    {
        return [
            'plural s' => ['documents', 'document'],
            'plural sses' => ['classes', 'class'],
            'plural ies' => ['queries', 'queri'],
            'keeps ss' => ['less', 'less'],
            'ing suffix' => ['indexing', 'index'],
            'ed suffix' => ['ranked', 'rank'],
            'eed suffix' => ['agreed', 'agre'],
            'doubled consonant' => ['stopping', 'stop'],
            'cvc adds e' => ['filing', 'file'],
            'y to i' => ['happy', 'happi'],
            'ational to ate' => ['relational', 'relat'],
            'ization to ize' => ['normalization', 'normal'],
            'iveness to ive' => ['effectiveness', 'effect'],
            'biliti to ble' => ['sensibiliti', 'sensibl'],
            'ful removed' => ['hopeful', 'hope'],
            'ness removed' => ['goodness', 'good'],
            'ement kept when short' => ['cement', 'cement'],
            'ion needs s or t' => ['adoption', 'adopt'],
            'trailing e dropped' => ['probate', 'probat'],
            'double l reduced' => ['controll', 'control'],
            'short word untouched' => ['is', 'is'],
            'non ascii untouched' => ['straße', 'straße'],
            'digits untouched' => ['bm25', 'bm25'],
        ];
    }

    public function testIsIdempotentForAlreadyStemmedWords(): void
    {
        foreach (['document', 'index', 'rank', 'token'] as $stem) {
            self::assertSame($stem, $this->stemmer->stem($this->stemmer->stem($stem)));
        }
    }

    public function testRelatedWordsCollapseOntoTheSameStem(): void
    {
        $stems = array_map($this->stemmer->stem(...), ['index', 'indexes', 'indexing', 'indexed']);

        self::assertSame(['index'], array_values(array_unique($stems)));
    }

    public function testCachingStemmerReturnsIdenticalResults(): void
    {
        $caching = new CachingStemmer($this->stemmer);

        foreach (['documents', 'indexing', 'relational', 'goodness'] as $word) {
            self::assertSame($this->stemmer->stem($word), $caching->stem($word));
            self::assertSame($this->stemmer->stem($word), $caching->stem($word));
        }

        self::assertGreaterThan(0, $caching->statistics()['hits']);
    }
}
