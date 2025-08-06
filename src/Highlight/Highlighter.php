<?php

declare(strict_types=1);

namespace EsLite\Highlight;

use EsLite\Document\StoredDocument;
use EsLite\Search\PlannedQuery;

final class Highlighter
{
    public function __construct(private readonly MatchLocator $locator)
    {
    }

    public function highlight(
        StoredDocument $document,
        PlannedQuery $planned,
        HighlightOptions $options,
    ): array {
        $highlights = [];
        $fields = $document->fields();

        foreach ($options->fields as $field) {
            $text = $fields[$field] ?? '';

            if (trim($text) === '') {
                continue;
            }

            $fragment = $this->fragment($text, $planned, $options);

            if ($fragment !== null) {
                $highlights[$field] = [$fragment];
            }
        }

        if (!isset($highlights['body']) && in_array('body', $options->fields, true)) {
            $body = $fields['body'] ?? '';

            if (trim($body) !== '') {
                $highlights['body'] = [$this->excerpt($body, $options)];
            }
        }

        return $highlights;
    }

    private function fragment(string $text, PlannedQuery $planned, HighlightOptions $options): ?string
    {
        $spans = $this->locator->locate($text, $planned->terms, $planned->phrases);

        if ($spans === []) {
            return null;
        }

        $length = strlen($text);
        $start = max(0, $spans[0]->start - intdiv($options->fragmentSize, 3));
        $end = min($length, $start + $options->fragmentSize);
        $output = $start > 0 ? $options->ellipsis . ' ' : '';
        $cursor = $start;

        foreach ($spans as $span) {
            if ($span->start < $cursor || $span->end > $end) {
                continue;
            }

            $output .= $this->escape(substr($text, $cursor, $span->start - $cursor));
            $output .= $options->preTag
                . $this->escape(substr($text, $span->start, $span->end - $span->start))
                . $options->postTag;
            $cursor = $span->end;
        }

        $output .= $this->escape(substr($text, $cursor, max(0, $end - $cursor)));

        if ($end < $length) {
            $output .= ' ' . $options->ellipsis;
        }

        return trim((string) preg_replace('/\s+/u', ' ', $output));
    }

    private function excerpt(string $text, HighlightOptions $options): string
    {
        $excerpt = substr($text, 0, $options->fragmentSize);
        $truncated = strlen($text) > $options->fragmentSize;

        if ($truncated) {
            $lastSpace = strrpos($excerpt, ' ');

            if ($lastSpace !== false && $lastSpace > $options->fragmentSize / 2) {
                $excerpt = substr($excerpt, 0, $lastSpace);
            }
        }

        $formatted = trim((string) preg_replace('/\s+/u', ' ', $this->escape($excerpt)));

        return $truncated ? $formatted . ' ' . $options->ellipsis : $formatted;
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}
