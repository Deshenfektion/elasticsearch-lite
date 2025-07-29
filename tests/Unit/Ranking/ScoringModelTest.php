<?php

declare(strict_types=1);

namespace EsLite\Tests\Unit\Ranking;

use EsLite\Ranking\TfIdfModel;
use PHPUnit\Framework\TestCase;

final class ScoringModelTest extends TestCase
{
    public function testIdfFallsAsDocumentFrequencyRises(): void
    {
        $model = new TfIdfModel();

        self::assertGreaterThan($model->idf(500, 1000), $model->idf(2, 1000));
    }

    public function testIdfIsZeroForAnEmptyCollection(): void
    {
        self::assertSame(0.0, (new TfIdfModel())->idf(0, 0));
    }

    public function testScoreGrowsWithTermFrequency(): void
    {
        $model = new TfIdfModel();
        $idf = $model->idf(10, 1000);

        self::assertGreaterThan($model->termScore($idf, 1, 100, 100.0), $model->termScore($idf, 2, 100, 100.0));
    }

    public function testRepeatedOccurrencesAreDamped(): void
    {
        $model = new TfIdfModel();
        $idf = 1.0;

        $firstStep = $model->termScore($idf, 2, 100, 100.0) - $model->termScore($idf, 1, 100, 100.0);
        $laterStep = $model->termScore($idf, 20, 100, 100.0) - $model->termScore($idf, 19, 100, 100.0);

        self::assertGreaterThan($laterStep, $firstStep);
    }

    public function testLongerFieldsScoreLowerForTheSameTermFrequency(): void
    {
        $model = new TfIdfModel();
        $idf = $model->idf(10, 1000);

        self::assertGreaterThan($model->termScore($idf, 3, 400, 100.0), $model->termScore($idf, 3, 20, 100.0));
    }

    public function testLengthNormalisationCanBeDisabled(): void
    {
        $flat = new TfIdfModel(false);

        self::assertSame($flat->termScore(1.0, 4, 10, 50.0), $flat->termScore(1.0, 4, 1000, 50.0));
    }

    public function testZeroTermFrequencyScoresZero(): void
    {
        self::assertSame(0.0, (new TfIdfModel())->termScore(2.0, 0, 100, 100.0));
    }

    public function testParametersAreReported(): void
    {
        self::assertSame(['length_normalisation' => true], (new TfIdfModel())->parameters());
    }
}
