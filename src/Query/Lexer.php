<?php

declare(strict_types=1);

namespace EsLite\Query;

use EsLite\Query\Exception\QueryParseException;

final class Lexer
{
    private const int MAX_LENGTH = 512;

    private const array OPERATORS = [
        'and' => TokenType::And,
        'or' => TokenType::Or,
        'not' => TokenType::Not,
    ];

    public function tokenize(string $input): array
    {
        if (strlen($input) > self::MAX_LENGTH) {
            throw QueryParseException::tooLong(self::MAX_LENGTH);
        }

        $tokens = [];
        $length = strlen($input);
        $offset = 0;

        while ($offset < $length) {
            $character = $input[$offset];

            if (ctype_space($character)) {
                $offset++;

                continue;
            }

            $simple = $this->simpleToken($character, $offset);

            if ($simple !== null) {
                $tokens[] = $simple;
                $offset++;

                continue;
            }

            if ($character === '"') {
                [$token, $offset] = $this->readPhrase($input, $offset);
                $tokens[] = $token;

                continue;
            }

            [$token, $offset] = $this->readWord($input, $offset);
            $tokens[] = $token;
        }

        $tokens[] = new Token(TokenType::End, '', $length);

        return $tokens;
    }

    private function simpleToken(string $character, int $offset): ?Token
    {
        $type = match ($character) {
            ':' => TokenType::Colon,
            '(' => TokenType::LeftParen,
            ')' => TokenType::RightParen,
            '+' => TokenType::Plus,
            '-' => TokenType::Minus,
            '!' => TokenType::Not,
            default => null,
        };

        return $type === null ? null : new Token($type, $character, $offset);
    }

    private function readPhrase(string $input, int $offset): array
    {
        $start = $offset;
        $offset++;
        $value = '';
        $length = strlen($input);

        while ($offset < $length) {
            $character = $input[$offset];

            if ($character === '\\' && $offset + 1 < $length) {
                $value .= $input[$offset + 1];
                $offset += 2;

                continue;
            }

            if ($character === '"') {
                return [new Token(TokenType::Phrase, $value, $start), $offset + 1];
            }

            $value .= $character;
            $offset++;
        }

        throw QueryParseException::unterminatedPhrase($start);
    }

    private function readWord(string $input, int $offset): array
    {
        $start = $offset;
        $value = '';
        $length = strlen($input);

        while ($offset < $length) {
            $character = $input[$offset];

            if (ctype_space($character) || in_array($character, [':', '(', ')', '"'], true)) {
                break;
            }

            $value .= $character;
            $offset++;
        }

        if ($value === '') {
            throw QueryParseException::unexpectedToken($input[$start], $start);
        }

        $keyword = self::OPERATORS[strtolower($value)] ?? null;

        return [new Token($keyword ?? TokenType::Word, $value, $start), $offset];
    }
}
