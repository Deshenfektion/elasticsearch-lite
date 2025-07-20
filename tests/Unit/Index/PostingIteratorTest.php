<?php

declare(strict_types=1);

namespace EsLite\Tests\Unit\Index;

use EsLite\Index\DocIdIterator;
use EsLite\Index\PostingIterator;
use PHPUnit\Framework\TestCase;

final class PostingIteratorTest extends TestCase
{
    public function testWalksDocumentsInOrder(): void
    {
        $iterator = new PostingIterator([2, 5, 9]);
        $seen = [];

        while (($docId = $iterator->next()) !== DocIdIterator::NO_MORE_DOCS) {
            $seen[] = $docId;
        }

        self::assertSame([2, 5, 9], $seen);
    }

    public function testAdvanceLandsOnTheFirstDocumentAtOrAfterTheTarget(): void
    {
        $iterator = new PostingIterator([1, 4, 8, 15, 16, 23, 42]);

        self::assertSame(8, $iterator->advance(6));
        self::assertSame(15, $iterator->advance(15));
        self::assertSame(23, $iterator->advance(17));
    }

    public function testAdvanceIsIdempotentWhenAlreadyPositioned(): void
    {
        $iterator = new PostingIterator([3, 6, 9]);

        self::assertSame(6, $iterator->advance(5));
        self::assertSame(6, $iterator->advance(5));
        self::assertSame(6, $iterator->advance(6));
    }

    public function testAdvanceBeyondTheLastDocumentExhaustsTheIterator(): void
    {
        $iterator = new PostingIterator([1, 2, 3]);

        self::assertSame(DocIdIterator::NO_MORE_DOCS, $iterator->advance(99));
        self::assertSame(DocIdIterator::NO_MORE_DOCS, $iterator->next());
    }

    public function testEmptyListIsImmediatelyExhausted(): void
    {
        $iterator = new PostingIterator([]);

        self::assertSame(0, $iterator->cost());
        self::assertSame(DocIdIterator::NO_MORE_DOCS, $iterator->next());
        self::assertSame(DocIdIterator::NO_MORE_DOCS, $iterator->advance(1));
    }

    public function testGallopingFindsTargetsInLargeLists(): void
    {
        $docIds = range(0, 99999, 3);
        $iterator = new PostingIterator($docIds);

        self::assertSame(30000, $iterator->advance(29999));
        self::assertSame(60000, $iterator->advance(60000));
        self::assertSame(99999, $iterator->advance(99998));
    }

    public function testCostReportsPostingCount(): void
    {
        self::assertSame(4, (new PostingIterator([1, 2, 3, 4]))->cost());
    }

    public function testResetRestartsIteration(): void
    {
        $iterator = new PostingIterator([7, 8]);
        $iterator->next();
        $iterator->reset();

        self::assertSame(7, $iterator->next());
    }
}
