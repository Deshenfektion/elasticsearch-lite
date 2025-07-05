<?php

declare(strict_types=1);

namespace EsLite\Support\Database;

final class RowPlaceholders
{
    public static function build(int $columns, int $rows): string
    {
        $row = '(' . implode(', ', array_fill(0, $columns, '?')) . ')';

        return implode(', ', array_fill(0, $rows, $row));
    }

    public static function list(int $count): string
    {
        return implode(', ', array_fill(0, max($count, 1), '?'));
    }
}
