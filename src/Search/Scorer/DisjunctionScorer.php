<?php

declare(strict_types=1);

namespace EsLite\Search\Scorer;

use EsLite\Ranking\Explanation;

final class DisjunctionScorer implements Scorer
{
    private array $heap = [];

    private array $matched = [];

    private int $size = 0;

    private int $current = -1;

    private bool $initialised = false;

    public function __construct(
        private readonly array $scorers,
        private readonly int $minimumShouldMatch = 1,
        private readonly bool $coordination = true,
    ) {
    }

    public function docId(): int
    {
        return $this->current;
    }

    public function next(): int
    {
        if (!$this->initialised) {
            $this->initialise();

            return $this->collect();
        }

        foreach ($this->matched as $scorer) {
            if ($scorer->next() !== self::NO_MORE_DOCS) {
                $this->push($scorer);
            }
        }

        $this->matched = [];

        return $this->collect();
    }

    public function advance(int $target): int
    {
        if ($this->current >= $target && $this->current >= 0) {
            return $this->current;
        }

        if (!$this->initialised) {
            $this->initialise();
        }

        foreach ($this->matched as $scorer) {
            if ($scorer->advance($target) !== self::NO_MORE_DOCS) {
                $this->push($scorer);
            }
        }

        $this->matched = [];

        while ($this->size > 0 && $this->heap[0]->docId() < $target) {
            if ($this->heap[0]->advance($target) === self::NO_MORE_DOCS) {
                $this->pop();
            } else {
                $this->siftDown(0);
            }
        }

        return $this->collect();
    }

    public function cost(): int
    {
        $cost = 0;

        foreach ($this->scorers as $scorer) {
            $cost += $scorer->cost();
        }

        return $cost;
    }

    public function score(): float
    {
        $score = 0.0;

        foreach ($this->matched as $scorer) {
            $score += $scorer->score();
        }

        return $score * $this->coordinationFactor(count($this->matched));
    }

    public function explain(int $documentId): Explanation
    {
        $details = [];
        $value = 0.0;
        $matches = 0;

        foreach ($this->scorers as $scorer) {
            if ($scorer->advance($documentId) !== $documentId) {
                continue;
            }

            $explanation = $scorer->explain($documentId);
            $details[] = $explanation;
            $value += $explanation->value;
            $matches++;
        }

        $factor = $this->coordinationFactor($matches);

        if ($factor !== 1.0) {
            $details[] = Explanation::of(
                sprintf('coordination, %d of %d clauses matched', $matches, count($this->scorers)),
                $factor,
            );
        }

        return new Explanation('sum of optional clauses', $value * $factor, $details);
    }

    private function coordinationFactor(int $matches): float
    {
        $total = count($this->scorers);

        if (!$this->coordination || $total <= 1 || $matches <= 0) {
            return 1.0;
        }

        return $matches / $total;
    }

    private function initialise(): void
    {
        $this->initialised = true;

        foreach ($this->scorers as $scorer) {
            if ($scorer->next() !== self::NO_MORE_DOCS) {
                $this->push($scorer);
            }
        }
    }

    private function collect(): int
    {
        while ($this->size > 0) {
            $documentId = $this->heap[0]->docId();
            $this->matched = [];

            while ($this->size > 0 && $this->heap[0]->docId() === $documentId) {
                $this->matched[] = $this->heap[0];
                $this->pop();
            }

            if (count($this->matched) >= $this->minimumShouldMatch) {
                return $this->current = $documentId;
            }

            foreach ($this->matched as $scorer) {
                if ($scorer->next() !== self::NO_MORE_DOCS) {
                    $this->push($scorer);
                }
            }

            $this->matched = [];
        }

        return $this->current = self::NO_MORE_DOCS;
    }

    private function push(Scorer $scorer): void
    {
        $this->heap[$this->size] = $scorer;
        $index = $this->size++;

        while ($index > 0) {
            $parent = intdiv($index - 1, 2);

            if ($this->heap[$parent]->docId() <= $this->heap[$index]->docId()) {
                break;
            }

            $this->swap($parent, $index);
            $index = $parent;
        }
    }

    private function pop(): void
    {
        $this->size--;

        if ($this->size > 0) {
            $this->heap[0] = $this->heap[$this->size];
        }

        unset($this->heap[$this->size]);

        if ($this->size > 1) {
            $this->siftDown(0);
        }
    }

    private function siftDown(int $index): void
    {
        while (true) {
            $left = 2 * $index + 1;
            $right = $left + 1;
            $smallest = $index;

            if ($left < $this->size && $this->heap[$left]->docId() < $this->heap[$smallest]->docId()) {
                $smallest = $left;
            }

            if ($right < $this->size && $this->heap[$right]->docId() < $this->heap[$smallest]->docId()) {
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
