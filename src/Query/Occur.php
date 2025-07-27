<?php

declare(strict_types=1);

namespace EsLite\Query;

enum Occur: string
{
    case Must = 'must';
    case Should = 'should';
    case MustNot = 'must_not';

    public function symbol(): string
    {
        return match ($this) {
            self::Must => '+',
            self::Should => '',
            self::MustNot => '-',
        };
    }
}
