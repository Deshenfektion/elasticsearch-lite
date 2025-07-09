<?php

declare(strict_types=1);

namespace EsLite\Analysis;

interface TermNormaliser
{
    public function normalise(string $term): string;
}
