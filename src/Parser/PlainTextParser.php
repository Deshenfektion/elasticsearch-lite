<?php

declare(strict_types=1);

namespace EsLite\Parser;

use EsLite\Document\ParsedDocument;
use EsLite\Document\SourceDocument;
use EsLite\Parser\Exception\ParseException;

final class PlainTextParser implements Parser
{
    private const int TITLE_LENGTH = 120;

    public function mediaTypes(): array
    {
        return ['text/plain', 'text/markdown'];
    }

    public function parse(SourceDocument $document): ParsedDocument
    {
        $content = Text::normaliseWhitespace($document->content);

        if ($content === '') {
            throw ParseException::emptyContent($document->mediaType);
        }

        $title = $document->metadata->title;
        $body = $content;

        if ($title === null) {
            $heading = ltrim(Text::firstLine($content), '# ');
            $title = Text::truncate($heading, self::TITLE_LENGTH);
            $body = Text::withoutFirstLine($content) ?: $content;
        }

        return new ParsedDocument(
            $document->externalId,
            MediaType::normalise($document->mediaType),
            $title,
            $body,
            $document->metadata,
        );
    }
}
