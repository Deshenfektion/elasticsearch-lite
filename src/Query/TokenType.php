<?php

declare(strict_types=1);

namespace EsLite\Query;

enum TokenType: string
{
    case Word = 'word';
    case Phrase = 'phrase';
    case Colon = 'colon';
    case LeftParen = 'left_paren';
    case RightParen = 'right_paren';
    case Plus = 'plus';
    case Minus = 'minus';
    case And = 'and';
    case Or = 'or';
    case Not = 'not';
    case End = 'end';
}
