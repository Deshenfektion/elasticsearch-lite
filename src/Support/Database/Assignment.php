<?php

declare(strict_types=1);

namespace EsLite\Support\Database;

enum Assignment
{
    case Replace;
    case Increment;
}
