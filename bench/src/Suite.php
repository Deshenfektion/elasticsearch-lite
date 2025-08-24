<?php

declare(strict_types=1);

namespace EsLite\Bench;

use EsLite\Application;
use EsLite\Search\SearchRequest;
use EsLite\Support\Stopwatch;

final class Suite
{
    private array $queries = [];

    public function __construct(
        private readonly Application $app,
        private readonly CorpusGenerator $generator,
    ) {
    }

    public function ingest(int $documents): array
    {
        $indexing = $this->app->indexingService();
        $stopwatch = new Stopwatch();
        $tokens = 0;
        $indexed = 0;

        foreach ($this->generator->documents($documents) as $source) {
            $tokens += $indexing->ingest($source)->tokenCount;
            $indexed++;
        }

        $elapsed = max($stopwatch->elapsedMicros(), 1);
        $this->app->indexReader()->refresh();

        return [
            'documents' => $indexed,
            'tokens' => $tokens,
            'seconds' => round($elapsed / 1_000_000, 3),
            'documents_per_second' => round($indexed / ($elapsed / 1_000_000), 1),
            'tokens_per_second' => round($tokens / ($elapsed / 1_000_000), 0),
        ];
    }

    public function shapes(): array
    {
        if ($this->queries !== []) {
            return $this->queries;
        }

        $common = $this->generator->termAtRank(2);
        $secondCommon = $this->generator->termAtRank(5);
        $medium = $this->generator->termAtRank(60);
        $rare = $this->generator->termAtRank(1800);
        $prefix = substr($this->generator->termAtRank(1), 0, 3);

        return $this->queries = [
            'term (rare)' => ['q' => $rare],
            'term (common)' => ['q' => $common],
            'two terms (or)' => ['q' => sprintf('%s %s', $common, $secondCommon)],
            'two terms (and)' => ['q' => sprintf('+%s +%s', $common, $medium)],
            'three terms (or)' => ['q' => sprintf('%s %s %s', $common, $secondCommon, $medium)],
            'phrase' => ['q' => sprintf('"%s"', CorpusGenerator::PHRASE)],
            'prefix' => ['q' => $prefix . '*'],
            'field restricted' => ['q' => sprintf('title:%s', $common)],
            'negation' => ['q' => sprintf('%s -%s', $common, $secondCommon)],
            'filtered' => ['q' => $common, 'category' => $this->generator->categories()[0]],
            'filtered (facets off)' => ['q' => $common, 'category' => $this->generator->categories()[0], 'facets' => '0'],
            'deep page' => ['q' => $common, 'page' => 20],
            'browse (no query)' => ['q' => ''],
        ];
    }

    public function measure(int $iterations, bool $coldCaches): array
    {
        $search = $this->app->searchService();
        $config = $this->app->config();
        $results = [];

        foreach ($this->shapes() as $name => $parameters) {
            $latency = new Latency($name);
            $hits = 0;

            for ($iteration = 0; $iteration < $iterations; $iteration++) {
                if ($coldCaches) {
                    $this->app->indexReader()->refresh();
                }

                $request = SearchRequest::fromArray($parameters, $config);
                $stopwatch = new Stopwatch();
                $response = $search->search($request);
                $latency->record($stopwatch->elapsedMicros());
                $hits = $response->total;
            }

            $results[$name] = $latency->toArray() + ['hits' => $hits];
        }

        return $results;
    }

    public function indexProfile(): array
    {
        $statistics = $this->app->statisticsService()->statistics();
        $positions = (int) $this->app->connection()->selectValue(
            'SELECT SUM(LENGTH(positions)) FROM postings',
        );

        return [
            'documents' => $statistics['index']['documents'],
            'terms' => $statistics['index']['terms'],
            'postings' => $statistics['index']['postings'],
            'tokens' => $statistics['index']['tokens'],
            'position_bytes' => $positions,
            'bytes_per_position' => $statistics['index']['tokens'] > 0
                ? round($positions / $statistics['index']['tokens'], 2)
                : 0.0,
        ];
    }

    public function updateProfile(int $documents): array
    {
        $indexing = $this->app->indexingService();
        $latency = new Latency('single document update');
        $count = 0;

        foreach ($this->generator->documents($documents) as $source) {
            $modified = $source->withContent($source->content . ' appended sentence for the update benchmark.');
            $stopwatch = new Stopwatch();
            $indexing->ingest($modified);
            $latency->record($stopwatch->elapsedMicros());
            $count++;
        }

        return $latency->toArray() + ['documents' => $count];
    }
}
