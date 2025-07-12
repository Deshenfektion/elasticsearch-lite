<?php

declare(strict_types=1);

namespace EsLite\Support;

use DateTimeImmutable;

interface Clock
{
    public function now(): DateTimeImmutable;

    public function monotonic(): float;
}
