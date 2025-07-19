<?php

declare(strict_types=1);

namespace EsLite\Index\Codec;

use InvalidArgumentException;

final class VarIntCodec
{
    public static function encodeSorted(array $values): string
    {
        if ($values === []) {
            return '';
        }

        sort($values);
        $encoded = '';
        $previous = 0;

        foreach ($values as $value) {
            $value = (int) $value;

            if ($value < 0) {
                throw new InvalidArgumentException('Positions must not be negative.');
            }

            $encoded .= self::encodeInt($value - $previous);
            $previous = $value;
        }

        return $encoded;
    }

    public static function decodeSorted(string $blob): array
    {
        if ($blob === '') {
            return [];
        }

        $values = [];
        $length = strlen($blob);
        $offset = 0;
        $running = 0;

        while ($offset < $length) {
            $shift = 0;
            $delta = 0;

            do {
                $byte = ord($blob[$offset++]);
                $delta |= ($byte & 0x7f) << $shift;
                $shift += 7;
            } while (($byte & 0x80) !== 0 && $offset < $length);

            $running += $delta;
            $values[] = $running;
        }

        return $values;
    }

    public static function encodeInt(int $value): string
    {
        $encoded = '';

        while ($value >= 0x80) {
            $encoded .= chr(($value & 0x7f) | 0x80);
            $value >>= 7;
        }

        return $encoded . chr($value);
    }
}
