<?php

declare(strict_types=1);

namespace EsLite\Console;

use EsLite\Application;
use EsLite\Document\SourceDocument;
use EsLite\Parser\MediaType;
use EsLite\Search\SearchHit;
use EsLite\Search\SearchRequest;
use Throwable;

final class Console
{
    public function __construct(
        private readonly Application $application,
        private readonly Output $output = new Output(),
    ) {
    }

    public function run(array $argv): int
    {
        $command = $argv[1] ?? 'help';
        $arguments = array_slice($argv, 2);

        try {
            return match ($command) {
                'migrate' => $this->migrate(),
                'seed' => $this->seed($arguments),
                'index' => $this->index($arguments),
                'delete' => $this->delete($arguments),
                'search' => $this->search($arguments),
                'suggest' => $this->suggest($arguments),
                'reindex' => $this->reindex(),
                'statistics', 'stats' => $this->statistics(),
                'health' => $this->health(),
                'routes' => $this->routes(),
                'help', '--help', '-h' => $this->help(),
                default => $this->unknown($command),
            };
        } catch (Throwable $exception) {
            $this->output->error($exception->getMessage());

            return 1;
        }
    }

    private function migrate(): int
    {
        $applied = $this->application->migrator()->migrate();

        if ($applied === []) {
            $this->output->success('Schema is up to date.');

            return 0;
        }

        foreach ($applied as $version) {
            $this->output->success(sprintf('Applied %s', $version));
        }

        return 0;
    }

    private function seed(array $arguments): int
    {
        $path = (string) $this->option($arguments, 'file', dirname(__DIR__, 2) . '/database/seeds/corpus.json');

        if (!is_readable($path)) {
            $this->output->error(sprintf('Seed file "%s" is not readable.', (string) $path));

            return 1;
        }

        $payload = json_decode((string) file_get_contents((string) $path), true);

        if (!is_array($payload)) {
            $this->output->error('Seed file must contain a JSON array of documents.');

            return 1;
        }

        $indexing = $this->application->indexingService();
        $counts = ['created' => 0, 'updated' => 0, 'unchanged' => 0];
        $tokens = 0;

        foreach ($payload as $entry) {
            $result = $indexing->ingest(SourceDocument::fromArray($entry));
            $counts[$result->status->value]++;
            $tokens += $result->tokenCount;
        }

        $this->output->success(sprintf(
            '%d created, %d updated, %d unchanged (%d tokens indexed)',
            $counts['created'],
            $counts['updated'],
            $counts['unchanged'],
            $tokens,
        ));

        return 0;
    }

    private function index(array $arguments): int
    {
        $path = $arguments[0] ?? null;

        if ($path === null || !is_readable($path)) {
            $this->output->error('Usage: console index <file> [--id=...] [--type=...]');

            return 1;
        }

        $source = new SourceDocument(
            (string) ($this->option($arguments, 'id', basename($path))),
            (string) ($this->option($arguments, 'type', MediaType::fromPath($path))),
            (string) file_get_contents($path),
        );

        $result = $this->application->indexingService()->ingest($source);
        $this->output->success(sprintf(
            '%s %s (%d tokens, %d terms, %.2f ms)',
            $result->externalId,
            $result->status->value,
            $result->tokenCount,
            $result->termCount,
            $result->tookMicros / 1000,
        ));

        return 0;
    }

    private function delete(array $arguments): int
    {
        $externalId = $arguments[0] ?? null;

        if ($externalId === null) {
            $this->output->error('Usage: console delete <document-id>');

            return 1;
        }

        if (!$this->application->indexingService()->delete($externalId)) {
            $this->output->warning(sprintf('Document "%s" is not indexed.', $externalId));

            return 1;
        }

        $this->output->success(sprintf('Deleted "%s".', $externalId));

        return 0;
    }

    private function search(array $arguments): int
    {
        $query = implode(' ', array_filter($arguments, static fn (string $arg): bool => !str_starts_with($arg, '--')));
        $request = SearchRequest::fromArray([
            'q' => $query,
            'size' => $this->option($arguments, 'size', '10'),
            'explain' => $this->option($arguments, 'explain', 'false'),
            'category' => $this->option($arguments, 'category'),
            'tag' => $this->option($arguments, 'tag'),
            'author' => $this->option($arguments, 'author'),
            'facets' => 'false',
        ], $this->application->config());

        $response = $this->application->searchService()->search($request);

        $this->output->title(sprintf(
            '%d hits in %.2f ms  (%s)',
            $response->total,
            $response->tookMicros / 1000,
            $response->query['rewritten'] ?? '',
        ));

        $rows = [];

        foreach ($response->hits as $position => $hit) {
            $rows[] = [
                sprintf('%d.', $position + 1 + $request->from),
                sprintf('%.4f', $hit->score),
                mb_substr($hit->document->title, 0, 60),
                $hit->document->category ?? '-',
            ];
        }

        $this->output->table($rows, ['#', 'score', 'title', 'category']);

        if ($request->explain) {
            foreach ($response->hits as $hit) {
                $this->output->line();
                $this->output->title($hit->document->title);
                $this->output->json($hit->explanation?->toArray() ?? []);
            }
        }

        foreach ($response->hits as $hit) {
            $this->printHighlights($hit);
        }

        return 0;
    }

    private function suggest(array $arguments): int
    {
        $prefix = implode(' ', array_filter($arguments, static fn (string $arg): bool => !str_starts_with($arg, '--')));
        $suggestions = $this->application->suggestService()->suggest($prefix);

        $rows = [];

        foreach ($suggestions['terms'] as $term) {
            $rows[] = [$term['term'], sprintf('%d docs', $term['documents'])];
        }

        $this->output->table($rows, ['term', 'documents']);

        return 0;
    }

    private function reindex(): int
    {
        $statistics = $this->application->reindexService()->reindex();
        $this->output->success(sprintf(
            'Reindexed %d documents (%d tokens) in %.0f ms',
            $statistics['documents'],
            $statistics['tokens'],
            $statistics['took_ms'],
        ));

        return 0;
    }

    private function statistics(): int
    {
        $this->output->json($this->application->statisticsService()->statistics());

        return 0;
    }

    private function health(): int
    {
        $health = $this->application->healthService()->check();
        $this->output->json($health);

        return $health['status'] === 'ok' ? 0 : 1;
    }

    private function routes(): int
    {
        foreach ($this->application->kernel()->routes() as $route) {
            $this->output->line('  ' . $route);
        }

        return 0;
    }

    private function help(): int
    {
        $this->output->title('elasticsearch-lite');
        $this->output->line();
        $this->output->detail('migrate', 'apply pending migrations');
        $this->output->detail('seed [--file=]', 'index the demo corpus');
        $this->output->detail('index <file>', 'index a single file');
        $this->output->detail('delete <id>', 'remove a document from the index');
        $this->output->detail('search <query>', 'run a query and print ranked hits');
        $this->output->detail('suggest <prefix>', 'print term completions');
        $this->output->detail('reindex', 'rebuild the index from stored documents');
        $this->output->detail('statistics', 'print index statistics as JSON');
        $this->output->detail('health', 'print health checks as JSON');
        $this->output->detail('routes', 'list HTTP routes');

        return 0;
    }

    private function unknown(string $command): int
    {
        $this->output->error(sprintf('Unknown command "%s".', $command));
        $this->help();

        return 1;
    }

    private function printHighlights(SearchHit $hit): void
    {
        $fragments = $hit->highlights['body'] ?? [];

        if ($fragments === []) {
            return;
        }

        $this->output->line();
        $this->output->line('  ' . $hit->document->title);
        $this->output->line('  ' . strip_tags(str_replace(['<mark>', '</mark>'], ['[', ']'], $fragments[0])));
    }

    private function option(array $arguments, string $name, ?string $default = null): ?string
    {
        foreach ($arguments as $argument) {
            if (str_starts_with($argument, '--' . $name . '=')) {
                return substr($argument, strlen($name) + 3);
            }

            if ($argument === '--' . $name) {
                return 'true';
            }
        }

        return $default;
    }
}
