<?php

declare(strict_types=1);

namespace EsLite\Tests\Support;

use EsLite\Application;
use EsLite\Document\SourceDocument;
use EsLite\Http\Request;
use EsLite\Http\Response;
use EsLite\Index\IndexingResult;
use EsLite\Search\SearchRequest;
use EsLite\Search\SearchResponse;
use EsLite\Support\Config;
use EsLite\Support\Database\Connection;
use EsLite\Support\Database\ConnectionFactory;
use PHPUnit\Framework\TestCase;

abstract class EngineTestCase extends TestCase
{
    protected Application $app;

    protected Connection $connection;

    protected function setUp(): void
    {
        parent::setUp();
        $this->boot();
    }

    protected function boot(array $overrides = []): void
    {
        $this->connection = ConnectionFactory::memory();
        $this->app = Application::boot($this->basePath(), $this->connection, $this->configuration($overrides));
        $this->app->migrator()->migrate();
    }

    protected function basePath(): string
    {
        return dirname(__DIR__, 2);
    }

    protected function configuration(array $overrides = []): Config
    {
        $config = Config::load($this->basePath() . '/config')
            ->with('app.search.cache.shared', false)
            ->with('app.search.cache.results.enabled', false)
            ->with('app.environment', 'testing');

        foreach ($overrides as $path => $value) {
            $config = $config->with($path, $value);
        }

        return $config;
    }

    protected function index(array $payload): IndexingResult
    {
        return $this->app->indexingService()->ingest(SourceDocument::fromArray($payload));
    }

    protected function indexAll(array $documents): array
    {
        return array_map($this->index(...), $documents);
    }

    protected function document(string $id, string $title, string $body, array $extra = []): array
    {
        return array_merge([
            'id' => $id,
            'title' => $title,
            'content' => $body,
            'media_type' => 'text/plain',
        ], $extra);
    }

    protected function search(string $query, array $parameters = []): SearchResponse
    {
        return $this->app->searchService()->search(
            SearchRequest::fromArray(array_merge(['q' => $query], $parameters), $this->app->config()),
        );
    }

    protected function titles(SearchResponse $response): array
    {
        return array_map(static fn ($hit): string => $hit->document->externalId, $response->hits);
    }

    protected function request(string $method, string $path, array $query = [], array $body = []): Response
    {
        return $this->app->kernel()->handle(Request::create($method, $path, $query, $body));
    }

    protected function seedCorpus(): array
    {
        return $this->indexAll([
            $this->document(
                'search-basics',
                'Search engine basics',
                'An inverted index maps terms to documents. The index is the core of every search engine.',
                ['category' => 'Guides', 'author' => 'Ada', 'tags' => ['index', 'basics'], 'published_at' => '2025-01-10'],
            ),
            $this->document(
                'ranking-models',
                'Ranking models compared',
                'BM25 and TF-IDF rank documents by term frequency and inverse document frequency.',
                ['category' => 'Ranking', 'author' => 'Bo', 'tags' => ['ranking', 'bm25'], 'published_at' => '2025-02-14'],
            ),
            $this->document(
                'posting-lists',
                'Posting lists and skipping',
                'A posting list stores document identifiers in sorted order so that intersection is a merge.',
                ['category' => 'Guides', 'author' => 'Ada', 'tags' => ['index', 'postings'], 'published_at' => '2025-03-02'],
            ),
            $this->document(
                'tokenizers',
                'Tokenizers and normalisation',
                'Tokenization splits text into terms; normalisation lowercases them and folds accents.',
                ['category' => 'Analysis', 'author' => 'Cy', 'tags' => ['analysis'], 'published_at' => '2025-04-20'],
            ),
        ]);
    }
}
