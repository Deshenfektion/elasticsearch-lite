<?php

declare(strict_types=1);

namespace EsLite\Search\Scorer;

use EsLite\Ranking\Explanation;

final class ConjunctionScorer implements Scorer
{
    private array $scorers;

    private int $current = -1;

    public function __construct(array $scorers)
    {
        usort($scorers, static fn (Scorer $left, Scorer $right): int => $left->cost() <=> $right->cost());
        $this->scorers = $scorers;
    }

    public function docId(): int
    {
        return $this->current;
    }

    public function next(): int
    {
        return $this->align($this->current < 0 ? 0 : $this->current + 1);
    }

    public function advance(int $target): int
    {
        if ($this->current >= $target && $this->current >= 0) {
            return $this->current;
        }

        return $this->align($target);
    }

    public function cost(): int
    {
        $cost = self::NO_MORE_DOCS;

        foreach ($this->scorers as $scorer) {
            $cost = min($cost, $scorer->cost());
        }

        return $cost === self::NO_MORE_DOCS ? 0 : $cost;
    }

    public function score(): float
    {
        $score = 0.0;

        foreach ($this->scorers as $scorer) {
            $score += $scorer->score();
        }

        return $score;
    }

    public function explain(int $documentId): Explanation
    {
        $details = [];
        $value = 0.0;

        foreach ($this->scorers as $scorer) {
            $explanation = $scorer->explain($documentId);
            $details[] = $explanation;
            $value += $explanation->value;
        }

        return new Explanation('sum of required clauses', $value, $details);
    }

    private function align(int $target): int
    {
        if ($this->scorers === []) {
            return $this->current = self::NO_MORE_DOCS;
        }

        $candidate = $this->scorers[0]->advance($target);
        $count = count($this->scorers);

        while ($candidate !== self::NO_MORE_DOCS) {
            $aligned = true;

            for ($index = 1; $index < $count; $index++) {
                $other = $this->scorers[$index]->advance($candidate);

                if ($other === self::NO_MORE_DOCS) {
                    return $this->current = self::NO_MORE_DOCS;
                }

                if ($other > $candidate) {
                    $candidate = $other;
                    $aligned = false;

                    break;
                }
            }

            if ($aligned) {
                return $this->current = $candidate;
            }

            $candidate = $this->scorers[0]->advance($candidate);
        }

        return $this->current = self::NO_MORE_DOCS;
    }
}
