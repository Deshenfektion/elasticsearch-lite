<?php

declare(strict_types=1);

namespace EsLite\Search\Scorer;

final class PhraseMatcher
{
    public static function count(array $positionLists, array $offsets = []): int
    {
        return count(self::positions($positionLists, $offsets));
    }

    public static function positions(array $positionLists, array $offsets = []): array
    {
        $size = count($positionLists);

        if ($size === 0) {
            return [];
        }

        if ($offsets === []) {
            $offsets = range(0, $size - 1);
        }

        if ($size === 1) {
            return $positionLists[0];
        }

        $lookups = [];

        for ($index = 1; $index < $size; $index++) {
            if ($positionLists[$index] === []) {
                return [];
            }

            $lookups[$index] = array_fill_keys($positionLists[$index], true);
        }

        $starts = [];
        $base = $offsets[0] ?? 0;

        foreach ($positionLists[0] as $start) {
            for ($index = 1; $index < $size; $index++) {
                if (!isset($lookups[$index][$start + ($offsets[$index] - $base)])) {
                    continue 2;
                }
            }

            $starts[] = $start;
        }

        return $starts;
    }
}
