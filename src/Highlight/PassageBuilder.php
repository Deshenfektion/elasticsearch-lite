<?php

declare(strict_types=1);

namespace EsLite\Highlight;

final class PassageBuilder
{
    private const int SNAP_WINDOW = 40;

    public function build(string $text, array $spans, HighlightOptions $options): array
    {
        if ($spans === []) {
            return [];
        }

        $length = strlen($text);
        $passages = [];
        $index = 0;
        $count = count($spans);

        while ($index < $count && count($passages) < $options->maxFragments * 2) {
            $span = $spans[$index];
            $start = $this->snapStart($text, max(0, $span->start - intdiv($options->fragmentSize, 3)));
            $end = $this->snapEnd($text, min($length, $start + $options->fragmentSize));
            $covered = [];

            while ($index < $count && $spans[$index]->end <= $end) {
                $covered[] = $spans[$index];
                $index++;
            }

            if ($covered === []) {
                $covered[] = $span;
                $end = min($length, $span->end + 1);
                $index++;
            }

            $passage = new Passage($start, $end, $covered, 0.0, $start > 0, $end < $length);
            $passages[] = $passage->withScore($this->score($passage));
        }

        usort($passages, static fn (Passage $left, Passage $right): int => $right->score <=> $left->score);
        $passages = array_slice($passages, 0, $options->maxFragments);
        usort($passages, static fn (Passage $left, Passage $right): int => $left->start <=> $right->start);

        return $passages;
    }

    private function score(Passage $passage): float
    {
        $score = $passage->distinctTerms() * 2.0;

        foreach ($passage->spans as $span) {
            $score += $span->weight();
        }

        return $score - $passage->start / 100000;
    }

    private function snapStart(string $text, int $offset): int
    {
        if ($offset <= 0) {
            return 0;
        }

        $limit = max(0, $offset - self::SNAP_WINDOW);

        for ($index = $offset; $index > $limit; $index--) {
            if ($this->isBoundary($text[$index - 1])) {
                return $index;
            }
        }

        return $this->alignUtf8($text, $offset);
    }

    private function snapEnd(string $text, int $offset): int
    {
        $length = strlen($text);

        if ($offset >= $length) {
            return $length;
        }

        $limit = min($length, $offset + self::SNAP_WINDOW);

        for ($index = $offset; $index < $limit; $index++) {
            if ($this->isBoundary($text[$index])) {
                return $index;
            }
        }

        return $this->alignUtf8($text, $offset);
    }

    private function isBoundary(string $character): bool
    {
        return $character === ' ' || $character === "\n" || $character === "\t";
    }

    private function alignUtf8(string $text, int $offset): int
    {
        while ($offset > 0 && $offset < strlen($text) && (ord($text[$offset]) & 0xc0) === 0x80) {
            $offset--;
        }

        return $offset;
    }
}
