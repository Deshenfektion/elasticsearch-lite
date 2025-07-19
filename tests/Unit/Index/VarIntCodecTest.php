<?php

declare(strict_types=1);

namespace EsLite\Tests\Unit\Index;

use EsLite\Index\Codec\VarIntCodec;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class VarIntCodecTest extends TestCase
{
    public function testRoundTripsSortedPositions(): void
    {
        $positions = [0, 1, 5, 12, 300, 4096, 100000];

        self::assertSame($positions, VarIntCodec::decodeSorted(VarIntCodec::encodeSorted($positions)));
    }

    public function testSortsInputBeforeEncoding(): void
    {
        self::assertSame([1, 4, 9], VarIntCodec::decodeSorted(VarIntCodec::encodeSorted([9, 1, 4])));
    }

    public function testEncodesEmptyListAsEmptyString(): void
    {
        self::assertSame('', VarIntCodec::encodeSorted([]));
        self::assertSame([], VarIntCodec::decodeSorted(''));
    }

    public function testSmallGapsUseOneByteEachBecauseOfDeltaEncoding(): void
    {
        $consecutive = range(0, 99);

        self::assertSame(100, strlen(VarIntCodec::encodeSorted($consecutive)));
    }

    public function testLargeValuesGrowByOneByteEverySevenBits(): void
    {
        self::assertSame(1, strlen(VarIntCodec::encodeInt(127)));
        self::assertSame(2, strlen(VarIntCodec::encodeInt(128)));
        self::assertSame(2, strlen(VarIntCodec::encodeInt(16383)));
        self::assertSame(3, strlen(VarIntCodec::encodeInt(16384)));
    }

    public function testDeltaEncodingBeatsFixedWidthForClusteredPositions(): void
    {
        $positions = range(1000, 1200);
        $encoded = VarIntCodec::encodeSorted($positions);

        self::assertLessThan(count($positions) * 4, strlen($encoded));
    }

    public function testRejectsNegativePositions(): void
    {
        $this->expectException(InvalidArgumentException::class);
        VarIntCodec::encodeSorted([1, -2]);
    }

    public function testHandlesRepeatedPositions(): void
    {
        self::assertSame([3, 3, 7], VarIntCodec::decodeSorted(VarIntCodec::encodeSorted([3, 7, 3])));
    }
}
