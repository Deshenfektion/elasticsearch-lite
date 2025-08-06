<?php

declare(strict_types=1);

namespace EsLite\Highlight;

use EsLite\Analysis\Analyzer;
use EsLite\Analysis\Token;
use EsLite\Search\Scorer\PhraseMatcher;

final class MatchLocator
{
    public function __construct(private readonly Analyzer $analyzer)
    {
    }

    public function locate(string $text, array $terms, array $phrases = []): array
    {
        if ($text === '' || ($terms === [] && $phrases === [])) {
            return [];
        }

        $wanted = array_fill_keys($terms, true);
        $spans = [];
        $byPosition = [];
        $positionsByTerm = [];

        foreach ($this->analyzer->analyse($text) as $token) {
            $byPosition[$token->position] = $token;
            $positionsByTerm[$token->term][] = $token->position;

            if (isset($wanted[$token->term])) {
                $spans[] = new MatchSpan($token->startOffset, $token->endOffset, $token->term);
            }
        }

        foreach ($phrases as $phrase) {
            foreach ($this->phraseSpans($phrase, $byPosition, $positionsByTerm) as $span) {
                $spans[] = $span;
            }
        }

        usort($spans, static fn (MatchSpan $left, MatchSpan $right): int => $left->start <=> $right->start);

        return $this->deduplicate($spans);
    }

    private function phraseSpans(array $phrase, array $byPosition, array $positionsByTerm): array
    {
        $positionLists = [];

        foreach ($phrase as $term) {
            if (!isset($positionsByTerm[$term])) {
                return [];
            }

            $positionLists[] = $positionsByTerm[$term];
        }

        $spans = [];
        $length = count($phrase);

        foreach (PhraseMatcher::positions($positionLists) as $start) {
            $first = $byPosition[$start] ?? null;
            $last = $byPosition[$start + $length - 1] ?? null;

            if (!$first instanceof Token || !$last instanceof Token) {
                continue;
            }

            $spans[] = new MatchSpan($first->startOffset, $last->endOffset, implode(' ', $phrase), true);
        }

        return $spans;
    }

    private function deduplicate(array $spans): array
    {
        $kept = [];

        foreach ($spans as $span) {
            $last = $kept === [] ? null : $kept[count($kept) - 1];

            if ($last instanceof MatchSpan && $last->overlaps($span)) {
                if ($span->end - $span->start > $last->end - $last->start) {
                    $kept[count($kept) - 1] = $span;
                }

                continue;
            }

            $kept[] = $span;
        }

        return $kept;
    }
}
