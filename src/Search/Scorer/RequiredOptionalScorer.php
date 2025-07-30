<?php

declare(strict_types=1);

namespace EsLite\Search\Scorer;

use EsLite\Ranking\Explanation;

final class RequiredOptionalScorer implements Scorer
{
    public function __construct(
        private readonly Scorer $required,
        private readonly array $optional,
    ) {
    }

    public function docId(): int
    {
        return $this->required->docId();
    }

    public function next(): int
    {
        return $this->required->next();
    }

    public function advance(int $target): int
    {
        return $this->required->advance($target);
    }

    public function cost(): int
    {
        return $this->required->cost();
    }

    public function score(): float
    {
        $documentId = $this->required->docId();
        $score = $this->required->score();

        foreach ($this->optional as $scorer) {
            if ($scorer->advance($documentId) === $documentId) {
                $score += $scorer->score();
            }
        }

        return $score;
    }

    public function explain(int $documentId): Explanation
    {
        $details = [$this->required->explain($documentId)];
        $value = $details[0]->value;

        foreach ($this->optional as $scorer) {
            if ($scorer->advance($documentId) !== $documentId) {
                continue;
            }

            $explanation = $scorer->explain($documentId);
            $details[] = $explanation;
            $value += $explanation->value;
        }

        return new Explanation('required clauses plus matching optional clauses', $value, $details);
    }
}
