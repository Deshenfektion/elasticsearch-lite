<?php

declare(strict_types=1);

namespace EsLite\Index;

use EsLite\Analysis\Analyzer;
use EsLite\Document\ParsedDocument;
use EsLite\Repository\CategoryRepository;
use EsLite\Repository\DocumentFieldRepository;
use EsLite\Repository\DocumentRepository;
use EsLite\Repository\IndexStateRepository;
use EsLite\Repository\PostingRepository;
use EsLite\Repository\TagRepository;
use EsLite\Repository\TermRepository;
use EsLite\Support\Database\Connection;
use EsLite\Support\Stopwatch;

final class IndexWriter
{
    public function __construct(
        private readonly Connection $connection,
        private readonly DocumentRepository $documents,
        private readonly DocumentFieldRepository $documentFields,
        private readonly TermRepository $terms,
        private readonly PostingRepository $postings,
        private readonly CategoryRepository $categories,
        private readonly TagRepository $tags,
        private readonly IndexStateRepository $state,
        private readonly Analyzer $analyzer,
        private readonly FieldRegistry $fields,
        private readonly IndexCache $cache,
        private readonly bool $storePositions = true,
    ) {
    }

    public function write(ParsedDocument $document, bool $force = false): IndexingResult
    {
        $stopwatch = new Stopwatch();
        $existing = $this->documents->findByExternalId($document->externalId);

        if (!$force && $existing !== null && $existing->checksum === $document->checksum()) {
            return new IndexingResult(
                $existing->id,
                $document->externalId,
                IndexingStatus::Unchanged,
                $existing->tokenCount,
            );
        }

        $result = $this->connection->transaction(
            fn (): IndexingResult => $this->replace($document, $existing?->id),
        );

        return $result->withTiming($stopwatch->elapsedMicros());
    }

    public function delete(string $externalId): bool
    {
        $existing = $this->documents->findByExternalId($externalId);

        if ($existing === null) {
            return false;
        }

        $this->connection->transaction(function () use ($existing): void {
            $this->terms->applyFrequencyDeltas($this->unindex($existing->id));
            $this->documents->delete($existing->id);
            $this->state->apply([IndexStateRepository::DOCUMENT_COUNT => -1]);
        });

        return true;
    }

    public function clear(): void
    {
        $this->connection->transaction(function (): void {
            $this->postings->truncate();
            $this->terms->truncate();
            $this->documentFields->truncate();
            $this->documents->truncate();
            $this->state->reset();
        });

        $this->cache->flush();
    }

    public function clearIndex(): void
    {
        $this->connection->transaction(function (): void {
            $this->postings->truncate();
            $this->terms->truncate();
            $this->documentFields->truncate();
            $this->state->reset();
            $this->state->apply([IndexStateRepository::DOCUMENT_COUNT => $this->documents->count()]);
        });

        $this->cache->flush();
    }

    private function replace(ParsedDocument $document, ?int $existingId): IndexingResult
    {
        $analysis = $this->analyse($document);
        $categoryId = $this->categories->ensure($document->metadata->category);
        $documentId = $this->documents->save($document, $categoryId, $analysis->tokenCount(), $existingId);
        $this->tags->sync($documentId, $document->metadata->tags);

        $deltas = $existingId === null ? [] : $this->unindex($existingId);
        $termIds = $this->terms->ensure($analysis->uniqueTerms());

        $this->postings->insert($analysis->postingRows($documentId, $termIds, $this->storePositions));
        $this->documentFields->replace($documentId, $analysis->fieldLengths());

        $deltas = $this->mergeDeltas($deltas, $analysis->frequencyDeltas());
        $this->terms->applyFrequencyDeltas($deltas);
        $this->state->apply($this->stateDeltas($analysis, $existingId === null));
        $this->cache->invalidate(array_keys($deltas));

        return new IndexingResult(
            $documentId,
            $document->externalId,
            $existingId === null ? IndexingStatus::Created : IndexingStatus::Updated,
            $analysis->tokenCount(),
            count($analysis->uniqueTerms()),
        );
    }

    private function analyse(ParsedDocument $document): DocumentAnalysis
    {
        $analysis = new DocumentAnalysis();

        foreach ($document->fields() as $field => $content) {
            if (!$this->fields->has($field) || trim($content) === '') {
                continue;
            }

            $analysis->add($this->fields->id($field), $this->analyzer->analyse($content));
        }

        return $analysis;
    }

    private function unindex(int $documentId): array
    {
        $rows = $this->postings->fetchForDocument($documentId);
        $lengths = $this->documentFields->lengths($documentId);
        $deltas = [];
        $seen = [];

        foreach ($rows as $row) {
            $term = (string) $row['term'];
            $deltas[$term] ??= [0, 0];
            $deltas[$term][1] -= (int) $row['term_frequency'];

            if (!isset($seen[$term])) {
                $deltas[$term][0] -= 1;
                $seen[$term] = true;
            }
        }

        $this->postings->deleteForDocument($documentId);
        $this->documentFields->deleteForDocument($documentId);

        $stateDeltas = ['posting_count' => -count($rows), 'token_count' => -array_sum($lengths)];

        foreach ($lengths as $fieldId => $length) {
            $stateDeltas[IndexStateRepository::fieldDocumentKey($fieldId)] = -1;
            $stateDeltas[IndexStateRepository::fieldLengthKey($fieldId)] = -$length;
        }

        $this->state->apply($stateDeltas);
        $this->cache->invalidate(array_keys($deltas));

        return $deltas;
    }

    private function stateDeltas(DocumentAnalysis $analysis, bool $isNew): array
    {
        $deltas = [
            'posting_count' => $analysis->postingCount(),
            'token_count' => $analysis->tokenCount(),
        ];

        if ($isNew) {
            $deltas[IndexStateRepository::DOCUMENT_COUNT] = 1;
        }

        foreach ($analysis->fieldLengths() as $fieldId => $length) {
            $deltas[IndexStateRepository::fieldDocumentKey($fieldId)] = 1;
            $deltas[IndexStateRepository::fieldLengthKey($fieldId)] = $length;
        }

        return $deltas;
    }

    private function mergeDeltas(array $left, array $right): array
    {
        foreach ($right as $term => [$documentDelta, $frequencyDelta]) {
            $left[$term] ??= [0, 0];
            $left[$term][0] += $documentDelta;
            $left[$term][1] += $frequencyDelta;
        }

        return $left;
    }
}
