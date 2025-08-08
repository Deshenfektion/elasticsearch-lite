<?php

declare(strict_types=1);

namespace EsLite\Highlight;

final class PassageFormatter
{
    public function format(string $text, Passage $passage, HighlightOptions $options): string
    {
        $output = $passage->truncatedStart ? $options->ellipsis . ' ' : '';
        $cursor = $passage->start;

        foreach ($passage->spans as $span) {
            if ($span->start < $cursor) {
                continue;
            }

            $output .= $this->escape(substr($text, $cursor, $span->start - $cursor));
            $output .= $options->preTag
                . $this->escape(substr($text, $span->start, $span->end - $span->start))
                . $options->postTag;
            $cursor = $span->end;
        }

        $output .= $this->escape(substr($text, $cursor, max(0, $passage->end - $cursor)));

        if ($passage->truncatedEnd) {
            $output .= ' ' . $options->ellipsis;
        }

        return trim((string) preg_replace('/\s+/u', ' ', $output));
    }

    public function plain(string $text, int $length, HighlightOptions $options): string
    {
        $excerpt = substr($text, 0, $length);
        $truncated = strlen($text) > $length;

        if ($truncated) {
            $lastSpace = strrpos($excerpt, ' ');

            if ($lastSpace !== false && $lastSpace > $length / 2) {
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
