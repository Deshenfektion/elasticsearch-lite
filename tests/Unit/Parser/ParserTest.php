<?php

declare(strict_types=1);

namespace EsLite\Tests\Unit\Parser;

use EsLite\Document\DocumentMetadata;
use EsLite\Document\InvalidDocument;
use EsLite\Document\SourceDocument;
use EsLite\Parser\Exception\ParseException;
use EsLite\Parser\Exception\UnsupportedMediaTypeException;
use EsLite\Parser\HtmlParser;
use EsLite\Parser\JsonParser;
use EsLite\Parser\MediaType;
use EsLite\Parser\ParserRegistry;
use EsLite\Parser\PlainTextParser;
use PHPUnit\Framework\TestCase;

final class ParserTest extends TestCase
{
    public function testPlainTextUsesFirstLineAsTitle(): void
    {
        $parsed = (new PlainTextParser())->parse(new SourceDocument(
            'doc-1',
            'text/plain',
            "Inverted index basics\n\nAn inverted index maps terms to documents.",
        ));

        self::assertSame('Inverted index basics', $parsed->title);
        self::assertSame('An inverted index maps terms to documents.', $parsed->body);
    }

    public function testPlainTextKeepsSuppliedTitleAndFullBody(): void
    {
        $parsed = (new PlainTextParser())->parse(new SourceDocument(
            'doc-2',
            'text/plain',
            "First line\nSecond line",
            new DocumentMetadata('Explicit title'),
        ));

        self::assertSame('Explicit title', $parsed->title);
        self::assertSame("First line\nSecond line", $parsed->body);
    }

    public function testPlainTextCollapsesWhitespace(): void
    {
        $parsed = (new PlainTextParser())->parse(new SourceDocument(
            'doc-3',
            'text/plain',
            "Title\r\n\n\n\nbody    with     spaces",
        ));

        self::assertSame('body with spaces', $parsed->body);
    }

    public function testPlainTextRejectsEmptyContent(): void
    {
        $this->expectException(ParseException::class);
        (new PlainTextParser())->parse(new SourceDocument('doc-4', 'text/plain', "\n \t "));
    }

    public function testHtmlExtractsTextAndMetadata(): void
    {
        $html = '<html><head><title>Highlighting</title>'
            . '<meta name="author" content="Jonas Weber">'
            . '<meta name="keywords" content="snippets, ui">'
            . '<style>body{color:red}</style></head>'
            . '<body><h1>Highlighting</h1><p>First paragraph.</p>'
            . '<script>console.log("noise")</script><p>Second paragraph.</p></body></html>';

        $parsed = (new HtmlParser())->parse(new SourceDocument('doc-5', 'text/html', $html));

        self::assertSame('Highlighting', $parsed->title);
        self::assertSame('Jonas Weber', $parsed->metadata->author);
        self::assertSame(['snippets', 'ui'], $parsed->metadata->tags);
        self::assertStringContainsString('First paragraph.', $parsed->body);
        self::assertStringContainsString('Second paragraph.', $parsed->body);
        self::assertStringNotContainsString('console.log', $parsed->body);
        self::assertStringNotContainsString('color:red', $parsed->body);
    }

    public function testHtmlSeparatesBlockElements(): void
    {
        $parsed = (new HtmlParser())->parse(new SourceDocument(
            'doc-6',
            'text/html',
            '<body><p>alpha</p><p>beta</p></body>',
        ));

        self::assertStringNotContainsString('alphabeta', $parsed->body);
    }

    public function testHtmlFallsBackToHeadingWhenTitleIsMissing(): void
    {
        $parsed = (new HtmlParser())->parse(new SourceDocument(
            'doc-7',
            'text/html',
            '<body><h1>Only a heading</h1><p>Body text.</p></body>',
        ));

        self::assertSame('Only a heading', $parsed->title);
    }

    public function testJsonMapsKnownFields(): void
    {
        $payload = json_encode([
            'title' => 'Designing an API',
            'body' => 'Paging needs an upper bound.',
            'author' => 'Ada',
            'category' => 'API Design',
            'tags' => ['rest', 'api'],
            'published_at' => '2025-08-01',
            'url' => 'https://example.test/api',
        ]);

        $parsed = (new JsonParser())->parse(new SourceDocument('doc-8', 'application/json', (string) $payload));

        self::assertSame('Designing an API', $parsed->title);
        self::assertSame('Paging needs an upper bound.', $parsed->body);
        self::assertSame('Ada', $parsed->metadata->author);
        self::assertSame(['rest', 'api'], $parsed->metadata->tags);
        self::assertSame('2025-08-01', $parsed->metadata->publishedAt?->format('Y-m-d'));
    }

    public function testJsonFallsBackToAlternativeKeys(): void
    {
        $payload = (string) json_encode(['name' => 'Alternative', 'content' => 'Body from content key.']);
        $parsed = (new JsonParser())->parse(new SourceDocument('doc-9', 'application/json', $payload));

        self::assertSame('Alternative', $parsed->title);
        self::assertSame('Body from content key.', $parsed->body);
    }

    public function testJsonFlattensUnknownStructures(): void
    {
        $payload = (string) json_encode(['sections' => [['heading' => 'A'], ['heading' => 'B']]]);
        $parsed = (new JsonParser())->parse(new SourceDocument('doc-10', 'application/json', $payload));

        self::assertStringContainsString('A', $parsed->body);
        self::assertStringContainsString('B', $parsed->body);
    }

    public function testJsonRejectsMalformedPayloads(): void
    {
        $this->expectException(ParseException::class);
        (new JsonParser())->parse(new SourceDocument('doc-11', 'application/json', '{"unclosed": '));
    }

    public function testMetadataFromTheRequestWinsOverParsedMetadata(): void
    {
        $payload = (string) json_encode(['title' => 'From payload', 'body' => 'Body.', 'author' => 'Payload author']);
        $parsed = (new JsonParser())->parse(new SourceDocument(
            'doc-12',
            'application/json',
            $payload,
            new DocumentMetadata('Explicit', null, 'Explicit author'),
        ));

        self::assertSame('Explicit', $parsed->title);
        self::assertSame('Explicit author', $parsed->metadata->author);
    }

    public function testRegistryResolvesParsersByMediaType(): void
    {
        $registry = ParserRegistry::default();

        self::assertTrue($registry->supports('text/plain'));
        self::assertTrue($registry->supports('application/json; charset=utf-8'));
        self::assertInstanceOf(HtmlParser::class, $registry->for('html'));
    }

    public function testRegistryRejectsUnknownMediaTypes(): void
    {
        $this->expectException(UnsupportedMediaTypeException::class);
        ParserRegistry::default()->for('application/pdf');
    }

    public function testMediaTypeNormalisation(): void
    {
        self::assertSame('text/plain', MediaType::normalise('TEXT/PLAIN; charset=utf-8'));
        self::assertSame('application/json', MediaType::normalise('json'));
        self::assertSame('text/html', MediaType::fromPath('/tmp/page.HTML'));
        self::assertSame('text/plain', MediaType::normalise(''));
    }

    public function testSourceDocumentRequiresIdAndContent(): void
    {
        $this->expectException(InvalidDocument::class);
        SourceDocument::fromArray(['content' => 'no id']);
    }

    public function testSourceDocumentAcceptsCommaSeparatedTags(): void
    {
        $source = SourceDocument::fromArray(['id' => 'x', 'content' => 'body', 'tags' => 'one, two ,two']);

        self::assertSame(['one', 'two'], $source->metadata->tags);
    }

    public function testSourceDocumentEncodesStructuredContent(): void
    {
        $source = SourceDocument::fromArray(['id' => 'x', 'content' => ['title' => 'T', 'body' => 'B']]);

        self::assertJson($source->content);
    }

    public function testChecksumChangesWithContent(): void
    {
        $first = (new PlainTextParser())->parse(new SourceDocument('doc-13', 'text/plain', 'Title\nalpha'));
        $second = (new PlainTextParser())->parse(new SourceDocument('doc-13', 'text/plain', 'Title\nbeta'));

        self::assertNotSame($first->checksum(), $second->checksum());
    }
}
