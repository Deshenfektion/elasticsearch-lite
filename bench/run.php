<?php

declare(strict_types=1);

use EsLite\Application;
use EsLite\Bench\CorpusGenerator;
use EsLite\Bench\Suite;
use EsLite\Console\Output;
use EsLite\Support\Config;
use EsLite\Support\Database\ConnectionFactory;

require dirname(__DIR__) . '/vendor/autoload.php';

$options = getopt('', ['docs::', 'iterations::', 'updates::', 'driver::', 'sqlite::', 'json::', 'keep']) ?: [];
$documents = (int) ($options['docs'] ?? getenv('BENCH_DOCS') ?: 2000);
$iterations = (int) ($options['iterations'] ?? 40);
$updates = (int) ($options['updates'] ?? 100);
$output = new Output(stream_isatty(STDOUT));

$config = Config::load(dirname(__DIR__) . '/config')
    ->with('app.search.cache.results.enabled', false)
    ->with('app.search.cache.shared', false)
    ->with('app.search.log_queries', false);

$driver = (string) ($options['driver'] ?? $config->string('app.database.driver'));
$sqlitePath = (string) ($options['sqlite'] ?? sys_get_temp_dir() . '/eslite-bench.sqlite');

if ($driver === 'sqlite') {
    if (!isset($options['keep']) && file_exists($sqlitePath)) {
        unlink($sqlitePath);
    }

    $connection = ConnectionFactory::sqlite($sqlitePath);
} else {
    $connection = ConnectionFactory::fromConfig($config->with('app.database.driver', $driver));
}

$app = Application::boot(dirname(__DIR__), $connection, $config);
$app->migrator()->migrate();

if (!isset($options['keep'])) {
    $app->indexWriter()->clear();
}

$generator = new CorpusGenerator();
$suite = new Suite($app, $generator);

$output->title(sprintf('elasticsearch-lite benchmark — %s, %d documents', $driver, $documents));
$output->line();

$ingest = $suite->ingest($documents);
$output->detail('indexed documents', (string) $ingest['documents']);
$output->detail('indexing seconds', (string) $ingest['seconds']);
$output->detail('documents/second', (string) $ingest['documents_per_second']);
$output->detail('tokens/second', (string) $ingest['tokens_per_second']);

$profile = $suite->indexProfile();
$output->line();
$output->detail('unique terms', (string) $profile['terms']);
$output->detail('postings', (string) $profile['postings']);
$output->detail('tokens', (string) $profile['tokens']);
$output->detail('position bytes', (string) $profile['position_bytes']);
$output->detail('bytes per position', (string) $profile['bytes_per_position']);

$updateProfile = $suite->updateProfile($updates);
$output->line();
$output->detail('update p50 (ms)', (string) $updateProfile['p50_ms']);
$output->detail('update p99 (ms)', (string) $updateProfile['p99_ms']);

$warm = $suite->measure($iterations, false);
$cold = $suite->measure(max(5, intdiv($iterations, 4)), true);

$rows = [];

foreach ($warm as $name => $measurement) {
    $rows[] = [
        $name,
        (string) $measurement['hits'],
        number_format($measurement['p50_ms'], 2),
        number_format($measurement['p90_ms'], 2),
        number_format($measurement['p99_ms'], 2),
        number_format($cold[$name]['p50_ms'], 2),
    ];
}

$output->line();
$output->table($rows, ['query shape', 'hits', 'p50 ms', 'p90 ms', 'p99 ms', 'cold p50']);

$report = [
    'generated_at' => gmdate('c'),
    'environment' => [
        'php' => PHP_VERSION,
        'driver' => $driver,
        'opcache' => function_exists('opcache_get_status') && opcache_get_status(false) !== false,
        'apcu' => function_exists('apcu_enabled') && apcu_enabled(),
        'documents_requested' => $documents,
        'iterations' => $iterations,
    ],
    'indexing' => $ingest,
    'index' => $profile,
    'updates' => $updateProfile,
    'queries_warm' => $warm,
    'queries_cold' => $cold,
];

$target = (string) ($options['json'] ?? dirname(__DIR__) . '/bench/out/' . $driver . '-' . $documents . '.json');

if (!is_dir(dirname($target))) {
    mkdir(dirname($target), 0o775, true);
}

file_put_contents($target, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n");
$output->line();
$output->success(sprintf('wrote %s', $target));
