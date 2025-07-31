<?php

declare(strict_types=1);

namespace EsLite\Search\Scorer;

use EsLite\Index\FieldRegistry;
use EsLite\Index\PostingList;
use EsLite\Index\Statistics\CollectionStatistics;
use EsLite\Ranking\Explanation;
use EsLite\Ranking\ScoringModel;

final class PhraseScorer implements Scorer
{
    private readonly Scorer $conjunction;

    private readonly float $idfSum;

    private readonly array $fieldIds;

    private int $current = -1;

    public function __construct(
        private readonly array $postingLists,
        private readonly ScoringModel $model,
        private readonly FieldRegistry $fields,
        private readonly CollectionStatistics $statistics,
        private readonly ?int $fieldId = null,
        private readonly float $boost = 1.0,
        private readonly array $offsets = [],
    ) {
        $idfSum = 0.0;
        $scorers = [];

        foreach ($this->postingLists as $list) {
            $idfSum += $this->model->idf($list->documentFrequency, $this->statistics->documentCount);
            $scorers[] = new TermScorer($list, $this->model, $this->fields, $this->statistics, $this->fieldId);
        }

        $this->idfSum = $idfSum;
        $this->conjunction = new ConjunctionScorer($scorers);
        $this->fieldIds = $this->fieldId === null ? $this->fields->ids() : [$this->fieldId];
    }

    public function docId(): int
    {
        return $this->current;
    }

    public function next(): int
    {
        return $this->findNext($this->conjunction->next());
    }

    public function advance(int $target): int
    {
        if ($this->current >= $target && $this->current >= 0) {
            return $this->current;
        }

        return $this->findNext($this->conjunction->advance($target));
    }

    public function cost(): int
    {
        return $this->conjunction->cost();
    }

    public function score(): float
    {
        return $this->scoreFor($this->current);
    }

    public function explain(int $documentId): Explanation
    {
        $details = [Explanation::of('sum of term idf', $this->idfSum)];
        $matches = $this->matches($documentId);

        foreach ($matches as $fieldId => $frequency) {
            $details[] = Explanation::of(sprintf(
                'field "%s", %d phrase occurrence(s)',
                $this->fields->name($fieldId),
                $frequency,
            ), (float) $frequency);
        }

        return new Explanation(
            sprintf('phrase "%s"', implode(' ', array_map(
                static fn (PostingList $list): string => $list->term,
                $this->postingLists,
            ))),
            $this->scoreFor($documentId),
            $details,
        );
    }

    private function findNext(int $candidate): int
    {
        while ($candidate !== self::NO_MORE_DOCS) {
            if ($this->matches($candidate) !== []) {
                return $this->current = $candidate;
            }

            $candidate = $this->conjunction->next();
        }

        return $this->current = self::NO_MORE_DOCS;
    }

    private function matches(int $documentId): array
    {
        if ($documentId < 0 || $documentId === self::NO_MORE_DOCS) {
            return [];
        }

        $matches = [];

        foreach ($this->fieldIds as $fieldId) {
            $positionLists = [];

            foreach ($this->postingLists as $list) {
                $posting = $list->posting($documentId, $fieldId);

                if ($posting === null) {
                    continue 2;
                }

                $positionLists[] = $posting->positions();
            }

            $frequency = PhraseMatcher::count($positionLists, $this->offsets);

            if ($frequency > 0) {
                $matches[$fieldId] = $frequency;
            }
        }

        return $matches;
    }

    private function scoreFor(int $documentId): float
    {
        $score = 0.0;

        foreach ($this->matches($documentId) as $fieldId => $frequency) {
            $posting = $this->postingLists[0]->posting($documentId, $fieldId);
            $length = $posting === null ? 0 : $posting->fieldLength;

            $score += $this->model->termScore(
                $this->idfSum,
                $frequency,
                $length,
                $this->statistics->averageFieldLength($fieldId),
            ) * $this->fields->boostById($fieldId);
        }

        return $score * $this->boost;
    }
}
