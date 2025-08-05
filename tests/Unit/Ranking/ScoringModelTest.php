<?php

declare(strict_types=1);

namespace EsLite\Tests\Unit\Ranking;

use EsLite\Ranking\Bm25Model;
use EsLite\Ranking\TfIdfModel;
use PHPUnit\Framework\TestCase;

final class ScoringModelTest extends TestCase
{
    public function testIdfFallsAsDocumentFrequencyRises(): void
    {
        foreach ([new Bm25Model(), new TfIdfModel()] as $model) {
            $rare = $model->idf(2, 1000);
            $common = $model->idf(500, 1000);

            self::assertGreaterThan($common, $rare, $model->name());
        }
    }

    public function testIdfIsZeroForAnEmptyCollection(): void
    {
        self::assertSame(0.0, (new Bm25Model())->idf(0, 0));
        self::assertSame(0.0, (new TfIdfModel())->idf(0, 0));
    }

    public function testScoreGrowsWithTermFrequency(): void
    {
        $model = new Bm25Model();
        $idf = $model->idf(10, 1000);

        $once = $model->termScore($idf, 1, 100, 100.0);
        $twice = $model->termScore($idf, 2, 100, 100.0);

        self::assertGreaterThan($once, $twice);
    }

    public function testBm25SaturatesTermFrequency(): void
    {
        $model = new Bm25Model();
        $idf = $model->idf(10, 1000);

        $firstStep = $model->termScore($idf, 2, 100, 100.0) - $model->termScore($idf, 1, 100, 100.0);
        $laterStep = $model->termScore($idf, 20, 100, 100.0) - $model->termScore($idf, 19, 100, 100.0);

        self::assertGreaterThan($laterStep, $firstStep);
    }

    public function testTfIdfDoesNotSaturateAsSharplyAsBm25(): void
    {
        $bm25 = new Bm25Model();
        $tfidf = new TfIdfModel(false);
        $idf = 1.0;

        $bm25Ratio = $bm25->termScore($idf, 16, 100, 100.0) / $bm25->termScore($idf, 1, 100, 100.0);
        $tfidfRatio = $tfidf->termScore($idf, 16, 100, 100.0) / $tfidf->termScore($idf, 1, 100, 100.0);

        self::assertGreaterThan($bm25Ratio, $tfidfRatio);
    }

    public function testLongerFieldsScoreLowerForTheSameTermFrequency(): void
    {
        $model = new Bm25Model();
        $idf = $model->idf(10, 1000);

        $short = $model->termScore($idf, 3, 20, 100.0);
        $long = $model->termScore($idf, 3, 400, 100.0);

        self::assertGreaterThan($long, $short);
    }

    public function testLengthNormalisationCanBeDisabledForBm25(): void
    {
        $normalised = new Bm25Model(1.2, 0.75);
        $flat = new Bm25Model(1.2, 0.0);
        $idf = 1.0;

        self::assertNotEqualsWithDelta(
            $normalised->termScore($idf, 3, 400, 100.0),
            $normalised->termScore($idf, 3, 20, 100.0),
            0.0001,
        );
        self::assertEqualsWithDelta(
            $flat->termScore($idf, 3, 400, 100.0),
            $flat->termScore($idf, 3, 20, 100.0),
            0.0001,
        );
    }

    public function testTfIdfLengthNormalisationCanBeDisabled(): void
    {
        $flat = new TfIdfModel(false);

        self::assertSame($flat->termScore(1.0, 4, 10, 50.0), $flat->termScore(1.0, 4, 1000, 50.0));
    }

    public function testZeroTermFrequencyScoresZero(): void
    {
        self::assertSame(0.0, (new Bm25Model())->termScore(2.0, 0, 100, 100.0));
        self::assertSame(0.0, (new TfIdfModel())->termScore(2.0, 0, 100, 100.0));
    }

    public function testParametersAreReported(): void
    {
        self::assertSame(['k1' => 1.5, 'b' => 0.5], (new Bm25Model(1.5, 0.5))->parameters());
        self::assertSame(['length_normalisation' => true], (new TfIdfModel())->parameters());
    }
}
