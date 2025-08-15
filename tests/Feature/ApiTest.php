<?php

declare(strict_types=1);

namespace EsLite\Tests\Feature;

use EsLite\Tests\Support\EngineTestCase;

final class ApiTest extends EngineTestCase
{
    public function testIndexingASingleDocumentReturnsCreated(): void
    {
        $response = $this->request('POST', '/documents', [], [
            'id' => 'doc-1',
            'title' => 'Inverted index',
            'content' => 'An inverted index maps terms to documents.',
            'category' => 'Guides',
            'tags' => ['index'],
        ]);

        self::assertSame(201, $response->status());
        self::assertSame('created', $response->decoded()['status']);
        self::assertGreaterThan(0, $response->decoded()['tokens']);
    }

    public function testReindexingTheSameDocumentReportsUnchanged(): void
    {
        $payload = ['id' => 'doc-1', 'title' => 'Stable', 'content' => 'Body stays the same.'];
        $this->request('POST', '/documents', [], $payload);
        $response = $this->request('POST', '/documents', [], $payload);

        self::assertSame(200, $response->status());
        self::assertSame('unchanged', $response->decoded()['status']);
    }

    public function testBulkIndexingReportsPerDocumentResults(): void
    {
        $response = $this->request('POST', '/documents', [], [
            'documents' => [
                ['id' => 'doc-1', 'content' => 'First document about indexes.'],
                ['id' => 'doc-2', 'content' => 'Second document about ranking.'],
            ],
        ]);

        self::assertSame(201, $response->status());
        self::assertSame(2, $response->decoded()['indexed']);
        self::assertSame(2, $response->decoded()['counts']['created']);
        self::assertCount(2, $response->decoded()['results']);
    }

    public function testFetchingAndDeletingADocument(): void
    {
        $this->request('POST', '/documents', [], ['id' => 'doc-1', 'content' => 'Body of the document.']);

        $show = $this->request('GET', '/documents/doc-1');
        self::assertSame(200, $show->status());
        self::assertSame('doc-1', $show->decoded()['id']);

        $delete = $this->request('DELETE', '/documents/doc-1');
        self::assertSame(200, $delete->status());
        self::assertSame('deleted', $delete->decoded()['status']);

        self::assertSame(404, $this->request('GET', '/documents/doc-1')->status());
    }

    public function testSearchEndpointReturnsRankedHitsWithHighlights(): void
    {
        $this->seedCorpus();
        $response = $this->request('GET', '/search', ['q' => 'inverted index']);
        $payload = $response->decoded();

        self::assertSame(200, $response->status());
        self::assertGreaterThan(0, $payload['total']);
        self::assertSame('search-basics', $payload['hits'][0]['id']);
        self::assertArrayHasKey('body', $payload['hits'][0]['highlights']);
        self::assertArrayHasKey('took_ms', $payload);
        self::assertSame('(invert index)', $payload['query']['rewritten']);
    }

    public function testSearchEndpointSupportsPagingAndFilters(): void
    {
        $this->seedCorpus();
        $payload = $this->request('GET', '/search', ['q' => '', 'category' => 'Guides', 'size' => 1, 'page' => 2])
            ->decoded();

        self::assertSame(2, $payload['total']);
        self::assertSame(2, $payload['page']);
        self::assertSame(2, $payload['pages']);
        self::assertCount(1, $payload['hits']);
    }

    public function testSearchEndpointReportsFacets(): void
    {
        $this->seedCorpus();
        $payload = $this->request('GET', '/search', ['q' => 'index'])->decoded();

        self::assertArrayHasKey('Guides', $payload['facets']['categories']);
        self::assertArrayHasKey('index', $payload['facets']['tags']);
    }

    public function testSearchEndpointRejectsInvalidSyntax(): void
    {
        $response = $this->request('GET', '/search', ['q' => '"unterminated']);

        self::assertSame(400, $response->status());
        self::assertSame('query_parse_error', $response->decoded()['error']['type']);
        self::assertArrayHasKey('position', $response->decoded()['error']['details']);
    }

    public function testSuggestEndpointCompletesTerms(): void
    {
        $this->seedCorpus();
        $payload = $this->request('GET', '/suggest', ['q' => 'ind'])->decoded();

        self::assertSame('ind', $payload['prefix']);
        self::assertNotSame([], $payload['terms']);
        self::assertSame('index', $payload['terms'][0]['term']);
    }

    public function testSuggestEndpointIgnoresShortPrefixes(): void
    {
        $this->seedCorpus();

        self::assertSame([], $this->request('GET', '/suggest', ['q' => 'i'])->decoded()['terms']);
    }

    public function testStatisticsEndpointDescribesTheIndex(): void
    {
        $this->seedCorpus();
        $payload = $this->request('GET', '/statistics')->decoded();

        self::assertSame(4, $payload['index']['documents']);
        self::assertGreaterThan(0, $payload['index']['terms']);
        self::assertSame(3.0, $payload['fields']['title']['boost']);
        self::assertSame('standard', $payload['analysis']['tokenizer']);
        self::assertContains($payload['ranking']['model'], ['bm25', 'tfidf']);
    }

    public function testHealthEndpointReportsOk(): void
    {
        $response = $this->request('GET', '/health');

        self::assertSame(200, $response->status());
        self::assertSame('ok', $response->decoded()['status']);
        self::assertTrue($response->decoded()['checks']['database']['ok']);
    }

    public function testReindexEndpointRebuildsTheIndex(): void
    {
        $this->seedCorpus();
        $response = $this->request('POST', '/reindex');

        self::assertSame(202, $response->status());
        self::assertSame(4, $response->decoded()['documents']);
    }

    public function testUnknownRoutesReturnNotFound(): void
    {
        $response = $this->request('GET', '/nope');

        self::assertSame(404, $response->status());
        self::assertSame('route_not_found', $response->decoded()['error']['type']);
    }

    public function testWrongMethodReturnsMethodNotAllowed(): void
    {
        $response = $this->request('POST', '/search');

        self::assertSame(405, $response->status());
        self::assertSame(['GET'], $response->decoded()['error']['details']['allowed']);
    }

    public function testMissingRequiredFieldsAreRejected(): void
    {
        $response = $this->request('POST', '/documents', [], ['title' => 'No id or content']);

        self::assertSame(422, $response->status());
        self::assertSame('invalid_document', $response->decoded()['error']['type']);
    }

    public function testEmptyBodyIsRejected(): void
    {
        $response = $this->request('POST', '/documents');

        self::assertSame(400, $response->status());
        self::assertSame('invalid_request', $response->decoded()['error']['type']);
    }

    public function testUnsupportedMediaTypeIsRejected(): void
    {
        $response = $this->request('POST', '/documents', [], [
            'id' => 'doc-1',
            'media_type' => 'application/pdf',
            'content' => 'binary-ish',
        ]);

        self::assertSame(415, $response->status());
        self::assertSame('unsupported_media_type', $response->decoded()['error']['type']);
    }

    public function testUnparsableDocumentIsRejected(): void
    {
        $response = $this->request('POST', '/documents', [], [
            'id' => 'doc-1',
            'media_type' => 'application/json',
            'content' => '{"broken": ',
        ]);

        self::assertSame(422, $response->status());
        self::assertSame('document_parse_error', $response->decoded()['error']['type']);
    }

    public function testCorsAndSecurityHeadersArePresent(): void
    {
        $headers = $this->request('GET', '/health')->headers();

        self::assertSame('*', $headers['access-control-allow-origin']);
        self::assertSame('nosniff', $headers['x-content-type-options']);
    }

    public function testPreflightRequestsAreAnswered(): void
    {
        $response = $this->request('OPTIONS', '/search');

        self::assertSame(204, $response->status());
        self::assertSame('', $response->body());
    }

    public function testApiPrefixIsOptional(): void
    {
        self::assertSame(200, $this->request('GET', '/api/health')->status());
        self::assertSame(200, $this->request('GET', '/health')->status());
    }

    public function testRoutesAreRegistered(): void
    {
        self::assertSame([
            'POST /documents',
            'GET /documents/{id}',
            'DELETE /documents/{id}',
            'POST /reindex',
            'GET /search',
            'GET /suggest',
            'GET /statistics',
            'GET /health',
        ], $this->app->kernel()->routes());
    }
}
