<?php

declare(strict_types=1);

namespace EsLite\Parser;

use EsLite\Document\ParsedDocument;
use EsLite\Document\SourceDocument;

interface Parser
{
    public function mediaTypes(): array;

    public function parse(SourceDocument $document): ParsedDocument;
}
