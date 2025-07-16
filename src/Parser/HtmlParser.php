<?php

declare(strict_types=1);

namespace EsLite\Parser;

use Dom\Element;
use Dom\HTMLDocument;
use Dom\Node;
use Dom\Text as DomText;
use EsLite\Document\DocumentMetadata;
use EsLite\Document\ParsedDocument;
use EsLite\Document\SourceDocument;
use EsLite\Parser\Exception\ParseException;
use Throwable;

final class HtmlParser implements Parser
{
    private const array STRIPPED = ['script', 'style', 'noscript', 'template', 'svg', 'iframe'];

    private const array BLOCKS = [
        'address', 'article', 'aside', 'blockquote', 'br', 'div', 'dd', 'dl', 'dt', 'figcaption',
        'figure', 'footer', 'form', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6', 'header', 'hr', 'li',
        'main', 'nav', 'ol', 'p', 'pre', 'section', 'table', 'td', 'th', 'tr', 'ul',
    ];

    private const int TITLE_LENGTH = 200;

    public function mediaTypes(): array
    {
        return ['text/html', 'application/xhtml+xml'];
    }

    public function parse(SourceDocument $document): ParsedDocument
    {
        $document = $document->withContent(trim($document->content));

        if ($document->content === '') {
            throw ParseException::emptyContent($document->mediaType);
        }

        $dom = $this->load($document->content, $document->mediaType);
        $this->strip($dom);

        $body = Text::normaliseWhitespace($this->extractText($dom->body ?? $dom->documentElement));

        if ($body === '') {
            throw ParseException::emptyContent($document->mediaType);
        }

        $metadata = $this->extractMetadata($dom)->merge($document->metadata);
        $title = $metadata->title ?? Text::truncate(Text::firstLine($body), self::TITLE_LENGTH);

        return new ParsedDocument(
            $document->externalId,
            MediaType::normalise($document->mediaType),
            $title,
            $body,
            $metadata,
        );
    }

    private function load(string $html, string $mediaType): HTMLDocument
    {
        try {
            return HTMLDocument::createFromString($html, LIBXML_NOERROR | LIBXML_HTML_NOIMPLIED);
        } catch (Throwable $exception) {
            throw ParseException::malformed($mediaType, $exception->getMessage());
        }
    }

    private function strip(HTMLDocument $dom): void
    {
        foreach (self::STRIPPED as $tag) {
            $nodes = [];

            foreach ($dom->getElementsByTagName($tag) as $node) {
                $nodes[] = $node;
            }

            foreach ($nodes as $node) {
                $node->parentNode?->removeChild($node);
            }
        }
    }

    private function extractMetadata(HTMLDocument $dom): DocumentMetadata
    {
        $title = $this->trimmed($this->firstText($dom, 'title')) ?? $this->trimmed($this->firstText($dom, 'h1'));
        $author = $this->trimmed($this->metaContent($dom, 'author'));
        $keywords = $this->metaContent($dom, 'keywords');
        $tags = $keywords === null ? [] : array_map(trim(...), explode(',', $keywords));

        return new DocumentMetadata(
            $title === null ? null : Text::truncate($title, self::TITLE_LENGTH),
            null,
            $author,
            $this->trimmed($this->metaContent($dom, 'category')),
            $tags,
        );
    }

    private function firstText(HTMLDocument $dom, string $tag): ?string
    {
        $node = $dom->getElementsByTagName($tag)->item(0);

        return $node?->textContent;
    }

    private function metaContent(HTMLDocument $dom, string $name): ?string
    {
        foreach ($dom->getElementsByTagName('meta') as $meta) {
            if (strtolower($meta->getAttribute('name') ?? '') === $name) {
                $content = $meta->getAttribute('content');

                return $content === '' ? null : $content;
            }
        }

        return null;
    }

    private function extractText(?Node $node): string
    {
        if ($node === null) {
            return '';
        }

        $parts = [];
        $this->walk($node, $parts);

        return implode('', $parts);
    }

    private function walk(Node $node, array &$parts): void
    {
        foreach ($node->childNodes as $child) {
            if ($child instanceof DomText) {
                $parts[] = $child->data;

                continue;
            }

            if (!$child instanceof Element) {
                continue;
            }

            $isBlock = in_array(strtolower($child->tagName), self::BLOCKS, true);

            if ($isBlock) {
                $parts[] = "\n";
            }

            $this->walk($child, $parts);

            if ($isBlock) {
                $parts[] = "\n";
            }
        }
    }

    private function trimmed(?string $value): ?string
    {
        if ($value === null) {
            return null;
        }

        $value = trim((string) preg_replace('/\s+/u', ' ', $value));

        return $value === '' ? null : $value;
    }
}
