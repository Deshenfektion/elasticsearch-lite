<?php

declare(strict_types=1);

namespace EsLite\Tests\Unit\Search;

use EsLite\Search\Collector\TopScoreCollector;
use EsLite\Search\ScoreDoc;
use PHPUnit\Framework\TestCase;

final class TopScoreCollectorTest extends TestCase
{
    public function testKeepsOnlyTheBestScoringDocuments(): void
    {
        $collector = new TopScoreCollector(3);

        foreach ([[1, 0.4], [2, 9.1], [3, 2.5], [4, 0.1], [5, 7.7]] as [$documentId, $score]) {
            $collector->collect($documentId, $score);
        }

        self::assertSame([2, 5, 3], $this->ids($collector));
    }

    public function testCountsEveryMatchEvenWhenNotKept(): void
    {
        $collector = new TopScoreCollector(2);

        foreach (range(1, 10) as $documentId) {
            $collector->collect($documentId, $documentId / 10);
        }

        self::assertSame(10, $collector->totalHits());
        self::assertCount(2, $collector->scoreDocs());
    }

    public function testTracksMaximumScore(): void
    {
        $collector = new TopScoreCollector(1);
        $collector->collect(1, 3.5);
        $collector->collect(2, 8.25);

        self::assertSame(8.25, $collector->maxScore());
    }

    public function testBreaksScoreTiesByDocumentId(): void
    {
        $collector = new TopScoreCollector(3);
        $collector->collect(9, 1.0);
        $collector->collect(4, 1.0);
        $collector->collect(7, 1.0);

        self::assertSame([4, 7, 9], $this->ids($collector));
    }

    public function testReportsTheCompetitiveThresholdOnceFull(): void
    {
        $collector = new TopScoreCollector(2);

        self::assertSame(0.0, $collector->minimumCompetitiveScore());

        $collector->collect(1, 5.0);
        $collector->collect(2, 3.0);

        self::assertSame(3.0, $collector->minimumCompetitiveScore());
    }

    public function testCollectingNothingProducesNoResults(): void
    {
        $collector = new TopScoreCollector(5);

        self::assertSame(0, $collector->totalHits());
        self::assertSame([], $collector->scoreDocs());
    }

    private function ids(TopScoreCollector $collector): array
    {
        return array_map(static fn (ScoreDoc $doc): int => $doc->documentId, $collector->scoreDocs());
    }
}
