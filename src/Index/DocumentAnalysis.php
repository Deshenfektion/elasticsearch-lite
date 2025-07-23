<?php

declare(strict_types=1);

namespace EsLite\Index;

use EsLite\Analysis\TokenStream;
use EsLite\Index\Codec\VarIntCodec;

final class DocumentAnalysis
{
    private array $fields = [];

    public function add(int $fieldId, TokenStream $stream): void
    {
        $terms = [];

        foreach ($stream as $token) {
            $terms[$token->term]['frequency'] = ($terms[$token->term]['frequency'] ?? 0) + 1;
            $terms[$token->term]['positions'][] = $token->position;
        }

        $this->fields[$fieldId] = [
            'length' => $stream->count(),
            'terms' => $terms,
        ];
    }

    public function fieldIds(): array
    {
        return array_keys($this->fields);
    }

    public function fieldLengths(): array
    {
        $lengths = [];

        foreach ($this->fields as $fieldId => $field) {
            $lengths[$fieldId] = $field['length'];
        }

        return $lengths;
    }

    public function tokenCount(): int
    {
        return array_sum($this->fieldLengths());
    }

    public function uniqueTerms(): array
    {
        $terms = [];

        foreach ($this->fields as $field) {
            foreach (array_keys($field['terms']) as $term) {
                $terms[$term] = true;
            }
        }

        return array_keys($terms);
    }

    public function postingRows(int $documentId, array $termIds, bool $withPositions): array
    {
        $rows = [];

        foreach ($this->fields as $fieldId => $field) {
            foreach ($field['terms'] as $term => $entry) {
                $termId = $termIds[$term] ?? null;

                if ($termId === null) {
                    continue;
                }

                $rows[] = [
                    'term_id' => $termId,
                    'field_id' => $fieldId,
                    'document_id' => $documentId,
                    'term_frequency' => min($entry['frequency'], 65535),
                    'field_length' => min($field['length'], 65535),
                    'positions' => $withPositions ? VarIntCodec::encodeSorted($entry['positions']) : null,
                ];
            }
        }

        return $rows;
    }

    public function frequencyDeltas(): array
    {
        $deltas = [];

        foreach ($this->fields as $field) {
            foreach ($field['terms'] as $term => $entry) {
                $deltas[$term] ??= [0, 0];
                $deltas[$term][1] += $entry['frequency'];
            }
        }

        foreach (array_keys($deltas) as $term) {
            $deltas[$term][0] = 1;
        }

        return $deltas;
    }

    public function postingCount(): int
    {
        $count = 0;

        foreach ($this->fields as $field) {
            $count += count($field['terms']);
        }

        return $count;
    }
}
