<?php

declare(strict_types=1);

namespace EsLite\Query\Exception;

use EsLite\Exception\EsLiteException;
use RuntimeException;

final class QueryParseException extends RuntimeException implements EsLiteException
{
    private function __construct(
        string $message,
        public readonly int $position,
    ) {
        parent::__construct($message);
    }

    public static function unterminatedPhrase(int $position): self
    {
        return new self(sprintf('Unterminated quoted phrase at position %d.', $position), $position);
    }

    public static function unexpectedToken(string $value, int $position): self
    {
        return new self(sprintf('Unexpected "%s" at position %d.', $value, $position), $position);
    }

    public static function unbalancedParenthesis(int $position): self
    {
        return new self(sprintf('Unbalanced parenthesis at position %d.', $position), $position);
    }

    public static function emptyGroup(int $position): self
    {
        return new self(sprintf('Empty group at position %d.', $position), $position);
    }

    public static function tooDeep(int $depth): self
    {
        return new self(sprintf('Query nests deeper than %d levels.', $depth), 0);
    }

    public static function unknownField(string $field, array $known): self
    {
        return new self(
            sprintf('Unknown field "%s". Searchable fields: %s.', $field, implode(', ', $known)),
            0,
        );
    }

    public static function tooLong(int $limit): self
    {
        return new self(sprintf('Query is longer than %d characters.', $limit), 0);
    }
}
