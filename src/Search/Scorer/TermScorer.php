<?php

declare(strict_types=1);

namespace EsLite\Search\Scorer;

use EsLite\Index\FieldRegistry;
use EsLite\Index\PostingIterator;
use EsLite\Index\PostingList;
use EsLite\Index\Statistics\CollectionStatistics;
use EsLite\Ranking\Explanation;
use EsLite\Ranking\ScoringModel;

final class TermScorer implements Scorer
{
    private readonly PostingIterator $iterator;

    private readonly float $idf;

    public function __construct(
        private readonly PostingList $postings,
        private readonly ScoringModel $model,
        private readonly FieldRegistry $fields,
        private readonly CollectionStatistics $statistics,
        private readonly ?int $fieldId = null,
        private readonly float $boost = 1.0,
    ) {
        $this->iterator = $this->postings->iterator($this->fieldId);
        $this->idf = $this->model->idf($this->postings->documentFrequency, $this->statistics->documentCount);
    }

    public function term(): string
    {
        return $this->postings->term;
    }

    public function docId(): int
    {
        return $this->iterator->docId();
    }

    public function next(): int
    {
        return $this->iterator->next();
    }

    public function advance(int $target): int
    {
        return $this->iterator->advance($target);
    }

    public function cost(): int
    {
        return $this->iterator->cost();
    }

    public function score(): float
    {
        return $this->scoreFor($this->docId());
    }

    public function explain(int $documentId): Explanation
    {
        $details = [
            Explanation::of(sprintf(
                'idf, %d documents contain "%s" out of %d',
                $this->postings->documentFrequency,
                $this->postings->term,
                $this->statistics->documentCount,
            ), $this->idf),
        ];

        foreach ($this->postings->postings($documentId) as $posting) {
            if ($this->fieldId !== null && $posting->fieldId !== $this->fieldId) {
                continue;
            }

            $field = $this->fields->name($posting->fieldId);
            $contribution = $this->model->termScore(
                $this->idf,
                $posting->termFrequency,
                $posting->fieldLength,
                $this->statistics->averageFieldLength($posting->fieldId),
            ) * $this->fields->boost($field) * $this->boost;

            $details[] = Explanation::of(
                sprintf(
                    'field "%s", tf %d, length %d, boost %.2f',
                    $field,
                    $posting->termFrequency,
                    $posting->fieldLength,
                    $this->fields->boost($field),
                ),
                $contribution,
            );
        }

        return new Explanation(
            sprintf('term "%s"', $this->postings->term),
            $this->scoreFor($documentId),
            $details,
        );
    }

    private function scoreFor(int $documentId): float
    {
        if ($documentId < 0 || $documentId === self::NO_MORE_DOCS) {
            return 0.0;
        }

        $score = 0.0;

        foreach ($this->postings->postings($documentId) as $posting) {
            if ($this->fieldId !== null && $posting->fieldId !== $this->fieldId) {
                continue;
            }

            $score += $this->model->termScore(
                $this->idf,
                $posting->termFrequency,
                $posting->fieldLength,
                $this->statistics->averageFieldLength($posting->fieldId),
            ) * $this->fields->boostById($posting->fieldId);
        }

        return $score * $this->boost;
    }
}
