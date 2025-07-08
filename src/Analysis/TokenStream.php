<?php

declare(strict_types=1);

namespace EsLite\Analysis;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

final class TokenStream implements Countable, IteratorAggregate
{
    private array $tokens;

    public function __construct(Token ...$tokens)
    {
        $this->tokens = $tokens;
    }

    public static function fromArray(array $tokens): self
    {
        return new self(...$tokens);
    }

    public function tokens(): array
    {
        return $this->tokens;
    }

    public function terms(): array
    {
        return array_map(static fn (Token $token): string => $token->term, $this->tokens);
    }

    public function uniqueTerms(): array
    {
        return array_values(array_unique($this->terms()));
    }

    public function frequencies(): array
    {
        $frequencies = [];

        foreach ($this->tokens as $token) {
            $frequencies[$token->term] = ($frequencies[$token->term] ?? 0) + 1;
        }

        return $frequencies;
    }

    public function positions(): array
    {
        $positions = [];

        foreach ($this->tokens as $token) {
            $positions[$token->term][] = $token->position;
        }

        return $positions;
    }

    public function offsets(): array
    {
        $offsets = [];

        foreach ($this->tokens as $token) {
            $offsets[$token->term][] = [$token->startOffset, $token->endOffset];
        }

        return $offsets;
    }

    public function isEmpty(): bool
    {
        return $this->tokens === [];
    }

    public function count(): int
    {
        return count($this->tokens);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->tokens);
    }
}
