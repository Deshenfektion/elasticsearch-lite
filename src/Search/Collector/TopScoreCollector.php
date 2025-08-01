<?php

declare(strict_types=1);

namespace EsLite\Search\Collector;

use EsLite\Search\ScoreDoc;

final class TopScoreCollector
{
    private array $heap = [];

    private int $size = 0;

    private int $totalHits = 0;

    private float $maxScore = 0.0;

    public function __construct(private readonly int $capacity)
    {
    }

    public function collect(int $documentId, float $score): void
    {
        $this->totalHits++;

        if ($score > $this->maxScore) {
            $this->maxScore = $score;
        }

        if ($this->size < $this->capacity) {
            $this->push(new ScoreDoc($documentId, $score));

            return;
        }

        if ($this->capacity === 0 || $score <= $this->heap[0]->score) {
            return;
        }

        $this->heap[0] = new ScoreDoc($documentId, $score);
        $this->siftDown(0);
    }

    public function minimumCompetitiveScore(): float
    {
        return $this->size < $this->capacity ? 0.0 : $this->heap[0]->score;
    }

    public function totalHits(): int
    {
        return $this->totalHits;
    }

    public function maxScore(): float
    {
        return $this->maxScore;
    }

    public function scoreDocs(): array
    {
        $docs = array_slice($this->heap, 0, $this->size);

        usort($docs, static function (ScoreDoc $left, ScoreDoc $right): int {
            return $right->score <=> $left->score ?: $left->documentId <=> $right->documentId;
        });

        return $docs;
    }

    private function push(ScoreDoc $doc): void
    {
        $this->heap[$this->size] = $doc;
        $index = $this->size++;

        while ($index > 0) {
            $parent = intdiv($index - 1, 2);

            if ($this->heap[$parent]->score <= $this->heap[$index]->score) {
                break;
            }

            $this->swap($parent, $index);
            $index = $parent;
        }
    }

    private function siftDown(int $index): void
    {
        while (true) {
            $left = 2 * $index + 1;
            $right = $left + 1;
            $smallest = $index;

            if ($left < $this->size && $this->heap[$left]->score < $this->heap[$smallest]->score) {
                $smallest = $left;
            }

            if ($right < $this->size && $this->heap[$right]->score < $this->heap[$smallest]->score) {
                $smallest = $right;
            }

            if ($smallest === $index) {
                return;
            }

            $this->swap($index, $smallest);
            $index = $smallest;
        }
    }

    private function swap(int $left, int $right): void
    {
        [$this->heap[$left], $this->heap[$right]] = [$this->heap[$right], $this->heap[$left]];
    }
}
