<?php

declare(strict_types=1);

namespace EsLite\Highlight;

use EsLite\Document\StoredDocument;
use EsLite\Search\PlannedQuery;

final class Highlighter
{
    public function __construct(
        private readonly MatchLocator $locator,
        private readonly PassageBuilder $builder = new PassageBuilder(),
        private readonly PassageFormatter $formatter = new PassageFormatter(),
    ) {
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

            $fragments = $this->fragments($text, $planned, $options);

            if ($fragments !== []) {
                $highlights[$field] = $fragments;
            }
        }

        if (!isset($highlights['body']) && in_array('body', $options->fields, true)) {
            $body = $fields['body'] ?? '';

            if (trim($body) !== '') {
                $highlights['body'] = [$this->formatter->plain($body, $options->fragmentSize, $options)];
            }
        }

        return $highlights;
    }

    private function fragments(string $text, PlannedQuery $planned, HighlightOptions $options): array
    {
        $spans = $this->locator->locate($text, $planned->terms, $planned->phrases);

        if ($spans === []) {
            return [];
        }

        if (strlen($text) <= $options->fragmentSize) {
            $passage = new Passage(0, strlen($text), $spans);

            return [$this->formatter->format($text, $passage, $options)];
        }

        $fragments = [];

        foreach ($this->builder->build($text, $spans, $options) as $passage) {
            $fragments[] = $this->formatter->format($text, $passage, $options);
        }

        return $fragments;
    }
}
