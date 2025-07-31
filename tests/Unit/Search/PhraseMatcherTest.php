<?php

declare(strict_types=1);

namespace EsLite\Tests\Unit\Search;

use EsLite\Search\Scorer\PhraseMatcher;
use PHPUnit\Framework\TestCase;

final class PhraseMatcherTest extends TestCase
{
    public function testFindsConsecutiveOccurrences(): void
    {
        self::assertSame(1, PhraseMatcher::count([[4], [5]]));
        self::assertSame([4], PhraseMatcher::positions([[4], [5]]));
    }

    public function testCountsEveryOccurrence(): void
    {
        self::assertSame(2, PhraseMatcher::count([[0, 10], [1, 11]]));
    }

    public function testRejectsNonConsecutivePositions(): void
    {
        self::assertSame(0, PhraseMatcher::count([[0], [2]]));
    }

    public function testHandlesThreeTermPhrases(): void
    {
        self::assertSame(1, PhraseMatcher::count([[3], [4], [5]]));
        self::assertSame(0, PhraseMatcher::count([[3], [4], [6]]));
    }

    public function testHonoursRelativeOffsetsWhenStopWordsLeftHoles(): void
    {
        self::assertSame(1, PhraseMatcher::count([[7], [9]], [0, 2]));
        self::assertSame(0, PhraseMatcher::count([[7], [8]], [0, 2]));
    }

    public function testSingleTermPhraseReturnsEveryPosition(): void
    {
        self::assertSame(3, PhraseMatcher::count([[1, 5, 9]]));
    }

    public function testEmptyInputsMatchNothing(): void
    {
        self::assertSame(0, PhraseMatcher::count([]));
        self::assertSame(0, PhraseMatcher::count([[1], []]));
    }
}
