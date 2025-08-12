<?php

declare(strict_types=1);

namespace EsLite;

use EsLite\Analysis\Analyzer;
use EsLite\Analysis\AnalyzerFactory;
use EsLite\Highlight\Highlighter;
use EsLite\Highlight\HighlightOptions;
use EsLite\Highlight\MatchLocator;
use EsLite\Http\Controller\DocumentController;
use EsLite\Http\Controller\HealthController;
use EsLite\Http\Controller\IndexController;
use EsLite\Http\Controller\SearchController;
use EsLite\Http\Controller\StatisticsController;
use EsLite\Http\Controller\SuggestController;
use EsLite\Http\ExceptionMapper;
use EsLite\Http\Kernel;
use EsLite\Http\Router;
use EsLite\Index\FieldRegistry;
use EsLite\Index\IndexCache;
use EsLite\Index\IndexReader;
use EsLite\Index\IndexWriter;
use EsLite\Index\TermDictionary;
use EsLite\Parser\ParserRegistry;
use EsLite\Query\QueryParser;
use EsLite\Ranking\RankingConfiguration;
use EsLite\Repository\CategoryRepository;
use EsLite\Repository\DocumentFieldRepository;
use EsLite\Repository\DocumentRepository;
use EsLite\Repository\FacetRepository;
use EsLite\Repository\IndexStateRepository;
use EsLite\Repository\PostingRepository;
use EsLite\Repository\SearchLogRepository;
use EsLite\Repository\TagRepository;
use EsLite\Repository\TermRepository;
use EsLite\Search\Filter\FilterCompiler;
use EsLite\Search\QueryPlanner;
use EsLite\Search\ScorerFactory;
use EsLite\Search\Searcher;
use EsLite\Service\HealthService;
use EsLite\Service\HitAssembler;
use EsLite\Service\IndexingService;
use EsLite\Service\ReindexService;
use EsLite\Service\SearchService;
use EsLite\Service\StatisticsService;
use EsLite\Service\SuggestService;
use EsLite\Support\Cache\Cache;
use EsLite\Support\Cache\LruCache;
use EsLite\Support\Cache\NullCache;
use EsLite\Support\Clock;
use EsLite\Support\Config;
use EsLite\Support\Database\Connection;
use EsLite\Support\Database\ConnectionFactory;
use EsLite\Support\Database\Migrator;
use EsLite\Support\Env;
use EsLite\Support\SystemClock;

final class Application
{
    private array $services = [];

    private function __construct(
        private readonly Config $config,
        private readonly Connection $connection,
        private readonly Clock $clock,
        private readonly string $basePath,
    ) {
    }

    public static function boot(?string $basePath = null, ?Connection $connection = null, ?Config $config = null): self
    {
        $basePath = $basePath ?? dirname(__DIR__);

        if ($config === null) {
            Env::loadFile($basePath . '/.env');
            $config = Config::load($basePath . '/config');
        }

        return new self(
            $config,
            $connection ?? ConnectionFactory::fromConfig($config),
            new SystemClock(),
            $basePath,
        );
    }

    public function config(): Config
    {
        return $this->config;
    }

    public function connection(): Connection
    {
        return $this->connection;
    }

    public function migrator(): Migrator
    {
        return $this->service(Migrator::class, fn (): Migrator => new Migrator(
            $this->connection,
            $this->basePath . '/database/migrations',
        ));
    }

    public function fields(): FieldRegistry
    {
        return $this->service(FieldRegistry::class, fn (): FieldRegistry => FieldRegistry::fromConfig($this->config));
    }

    public function analyzer(): Analyzer
    {
        return $this->service(Analyzer::class, fn (): Analyzer => AnalyzerFactory::fromConfig($this->config));
    }

    public function ranking(): RankingConfiguration
    {
        return $this->service(
            RankingConfiguration::class,
            fn (): RankingConfiguration => RankingConfiguration::fromConfig($this->config),
        );
    }

    public function indexCache(): IndexCache
    {
        return $this->service(IndexCache::class, fn (): IndexCache => IndexCache::fromConfig($this->config));
    }

    public function documents(): DocumentRepository
    {
        return $this->service(
            DocumentRepository::class,
            fn (): DocumentRepository => new DocumentRepository($this->connection, $this->clock),
        );
    }

    public function terms(): TermRepository
    {
        return $this->service(TermRepository::class, fn (): TermRepository => new TermRepository($this->connection));
    }

    public function postings(): PostingRepository
    {
        return $this->service(
            PostingRepository::class,
            fn (): PostingRepository => new PostingRepository($this->connection),
        );
    }

    public function documentFields(): DocumentFieldRepository
    {
        return $this->service(
            DocumentFieldRepository::class,
            fn (): DocumentFieldRepository => new DocumentFieldRepository($this->connection),
        );
    }

    public function categories(): CategoryRepository
    {
        return $this->service(
            CategoryRepository::class,
            fn (): CategoryRepository => new CategoryRepository($this->connection),
        );
    }

    public function tags(): TagRepository
    {
        return $this->service(TagRepository::class, fn (): TagRepository => new TagRepository($this->connection));
    }

    public function facets(): FacetRepository
    {
        return $this->service(FacetRepository::class, fn (): FacetRepository => new FacetRepository($this->connection));
    }

    public function indexState(): IndexStateRepository
    {
        return $this->service(
            IndexStateRepository::class,
            fn (): IndexStateRepository => new IndexStateRepository($this->connection),
        );
    }

    public function searchLogs(): SearchLogRepository
    {
        return $this->service(
            SearchLogRepository::class,
            fn (): SearchLogRepository => new SearchLogRepository($this->connection, $this->clock),
        );
    }

    public function dictionary(): TermDictionary
    {
        return $this->service(
            TermDictionary::class,
            fn (): TermDictionary => new TermDictionary($this->terms(), $this->indexCache()->terms()),
        );
    }

    public function indexReader(): IndexReader
    {
        return $this->service(IndexReader::class, fn (): IndexReader => new IndexReader(
            $this->dictionary(),
            $this->postings(),
            $this->indexState(),
            $this->fields(),
            $this->indexCache(),
        ));
    }

    public function indexWriter(): IndexWriter
    {
        return $this->service(IndexWriter::class, fn (): IndexWriter => new IndexWriter(
            $this->connection,
            $this->documents(),
            $this->documentFields(),
            $this->terms(),
            $this->postings(),
            $this->categories(),
            $this->tags(),
            $this->indexState(),
            $this->analyzer(),
            $this->fields(),
            $this->indexCache(),
            $this->config->bool('app.indexing.store_positions', true),
        ));
    }

    public function parsers(): ParserRegistry
    {
        return $this->service(ParserRegistry::class, static fn (): ParserRegistry => ParserRegistry::default());
    }

    public function queryParser(): QueryParser
    {
        return $this->service(QueryParser::class, fn (): QueryParser => QueryParser::withDefaultOperator(
            $this->config->string('app.search.default_operator', 'or'),
        ));
    }

    public function searcher(): Searcher
    {
        return $this->service(Searcher::class, fn (): Searcher => new Searcher(
            $this->indexReader(),
            new QueryPlanner(
                $this->indexReader(),
                $this->analyzer(),
                $this->config->int('app.search.max_expansions', 64),
            ),
            new ScorerFactory($this->ranking(), $this->fields()),
        ));
    }

    public function highlighter(): Highlighter
    {
        return $this->service(
            Highlighter::class,
            fn (): Highlighter => new Highlighter(new MatchLocator($this->analyzer())),
        );
    }

    public function highlightOptions(): HighlightOptions
    {
        return $this->service(
            HighlightOptions::class,
            fn (): HighlightOptions => HighlightOptions::fromConfig($this->config),
        );
    }

    public function resultCache(): Cache
    {
        return $this->service(Cache::class, function (): Cache {
            if (!$this->config->bool('app.search.cache.results.enabled', true)) {
                return new NullCache();
            }

            return new LruCache(
                $this->config->int('app.search.cache.results.entries', 256),
                $this->config->int('app.search.cache.results.ttl', 30),
            );
        });
    }

    public function indexingService(): IndexingService
    {
        return $this->service(IndexingService::class, fn (): IndexingService => new IndexingService(
            $this->parsers(),
            $this->indexWriter(),
            $this->documents(),
        ));
    }

    public function searchService(): SearchService
    {
        return $this->service(SearchService::class, fn (): SearchService => new SearchService(
            $this->queryParser(),
            $this->searcher(),
            new FilterCompiler($this->connection, $this->documents()),
            $this->documents(),
            $this->facets(),
            new HitAssembler(
                $this->documents(),
                $this->highlighter(),
                $this->searcher(),
                $this->highlightOptions(),
            ),
            $this->searchLogs(),
            $this->resultCache(),
            $this->config->bool('app.search.log_queries', true),
        ));
    }

    public function suggestService(): SuggestService
    {
        return $this->service(SuggestService::class, fn (): SuggestService => new SuggestService(
            $this->dictionary(),
            $this->searchLogs(),
            $this->analyzer(),
            $this->config->int('app.suggest.min_prefix', 2),
        ));
    }

    public function statisticsService(): StatisticsService
    {
        return $this->service(StatisticsService::class, fn (): StatisticsService => new StatisticsService(
            $this->indexReader(),
            $this->postings(),
            $this->categories(),
            $this->tags(),
            $this->searchLogs(),
            $this->fields(),
            $this->ranking(),
            $this->analyzer(),
        ));
    }

    public function reindexService(): ReindexService
    {
        return $this->service(ReindexService::class, fn (): ReindexService => new ReindexService(
            $this->documents(),
            $this->indexWriter(),
            $this->config->int('app.indexing.batch_size', 250),
        ));
    }

    public function healthService(): HealthService
    {
        return $this->service(HealthService::class, fn (): HealthService => new HealthService(
            $this->connection,
            $this->migrator(),
            $this->indexReader(),
        ));
    }

    public function kernel(): Kernel
    {
        return $this->service(Kernel::class, function (): Kernel {
            $router = new Router();
            $documents = new DocumentController($this->indexingService());
            $search = new SearchController($this->searchService(), $this->config);
            $suggest = new SuggestController($this->suggestService(), $this->config->int('app.suggest.size', 8));
            $index = new IndexController($this->reindexService());
            $statistics = new StatisticsController($this->statisticsService());
            $health = new HealthController($this->healthService());

            $router->post('/documents', $documents->store(...));
            $router->get('/documents/{id}', $documents->show(...));
            $router->delete('/documents/{id}', $documents->destroy(...));
            $router->post('/reindex', $index->reindex(...));
            $router->get('/search', $search->search(...));
            $router->get('/suggest', $suggest->suggest(...));
            $router->get('/statistics', $statistics->show(...));
            $router->get('/health', $health->show(...));

            return new Kernel(
                $router,
                new ExceptionMapper($this->config->string('app.environment', 'local') !== 'production'),
                $this->config->string('app.api.cors_origin', '*'),
            );
        });
    }

    private function service(string $key, callable $factory): mixed
    {
        return $this->services[$key] ??= $factory();
    }
}
