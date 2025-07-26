<?php

declare(strict_types=1);

namespace EsLite\Tests\Unit\Query;

use EsLite\Query\Lexer;
use EsLite\Query\Token;
use EsLite\Query\TokenType;
use PHPUnit\Framework\TestCase;

final class LexerTest extends TestCase
{
    private Lexer $lexer;

    protected function setUp(): void
    {
        $this->lexer = new Lexer();
    }

    public function testEmitsWordsAndEndToken(): void
    {
        $types = $this->types('index ranking');

        self::assertSame([TokenType::Word, TokenType::Word, TokenType::End], $types);
    }

    public function testRecognisesOperatorsCaseInsensitively(): void
    {
        self::assertSame(
            [TokenType::Word, TokenType::And, TokenType::Word, TokenType::Or, TokenType::Word, TokenType::End],
            $this->types('a and b OR c'),
        );
    }

    public function testRecognisesStructuralCharacters(): void
    {
        self::assertSame(
            [
                TokenType::LeftParen,
                TokenType::Word,
                TokenType::Colon,
                TokenType::Word,
                TokenType::RightParen,
                TokenType::Plus,
                TokenType::Word,
                TokenType::Minus,
                TokenType::Word,
                TokenType::End,
            ],
            $this->types('(title:bm25) +must -noise'),
        );
    }

    public function testCapturesPhraseContentWithoutQuotes(): void
    {
        $tokens = $this->lexer->tokenize('"posting list"');

        self::assertSame(TokenType::Phrase, $tokens[0]->type);
        self::assertSame('posting list', $tokens[0]->value);
    }

    public function testRecordsTokenPositions(): void
    {
        $tokens = $this->lexer->tokenize('index ranking');

        self::assertSame(0, $tokens[0]->position);
        self::assertSame(6, $tokens[1]->position);
    }

    public function testKeepsPunctuationInsideWords(): void
    {
        $tokens = $this->lexer->tokenize('c++ e-mail 8.4');

        self::assertSame(['c++', 'e-mail', '8.4'], [$tokens[0]->value, $tokens[1]->value, $tokens[2]->value]);
    }

    private function types(string $input): array
    {
        return array_map(static fn (Token $token): TokenType => $token->type, $this->lexer->tokenize($input));
    }
}
