<?php

declare(strict_types=1);

namespace EsLite\Parser;

use EsLite\Document\ParsedDocument;
use EsLite\Document\SourceDocument;
use EsLite\Parser\Exception\UnsupportedMediaTypeException;

final class ParserRegistry
{
    private array $parsers = [];

    public function __construct(Parser ...$parsers)
    {
        foreach ($parsers as $parser) {
            $this->register($parser);
        }
    }

    public static function default(): self
    {
        return new self(new PlainTextParser(), new HtmlParser(), new JsonParser());
    }

    public function register(Parser $parser): void
    {
        foreach ($parser->mediaTypes() as $mediaType) {
            $this->parsers[MediaType::normalise($mediaType)] = $parser;
        }
    }

    public function supports(string $mediaType): bool
    {
        return isset($this->parsers[MediaType::normalise($mediaType)]);
    }

    public function mediaTypes(): array
    {
        return array_keys($this->parsers);
    }

    public function parse(SourceDocument $document): ParsedDocument
    {
        return $this->for($document->mediaType)->parse($document);
    }

    public function for(string $mediaType): Parser
    {
        $normalised = MediaType::normalise($mediaType);

        return $this->parsers[$normalised]
            ?? throw UnsupportedMediaTypeException::for($normalised, $this->mediaTypes());
    }
}
