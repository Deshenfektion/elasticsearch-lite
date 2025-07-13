<?php

declare(strict_types=1);

namespace EsLite\Analysis;

final class Analyzer
{
    private array $filters;

    public function __construct(
        private readonly Tokenizer $tokenizer,
        TokenFilter ...$filters,
    ) {
        $this->filters = $filters;
    }

    public function analyse(string $text): TokenStream
    {
        $stream = $this->tokenizer->tokenize($text);

        foreach ($this->filters as $filter) {
            if ($stream->isEmpty()) {
                break;
            }

            $stream = $filter->apply($stream);
        }

        return $stream;
    }

    public function terms(string $text): array
    {
        return $this->analyse($text)->terms();
    }

    public function normaliseTerm(string $term): string
    {
        foreach ($this->filters as $filter) {
            if ($filter instanceof TermNormaliser) {
                $term = $filter->normalise($term);
            }
        }

        return $term;
    }

    public function withoutStemming(): self
    {
        $filters = array_values(array_filter(
            $this->filters,
            static fn (TokenFilter $filter): bool => !str_starts_with($filter->name(), 'stem:'),
        ));

        return new self($this->tokenizer, ...$filters);
    }

    public function describe(): array
    {
        return [
            'tokenizer' => 'standard',
            'filters' => array_map(static fn (TokenFilter $filter): string => $filter->name(), $this->filters),
        ];
    }
}
